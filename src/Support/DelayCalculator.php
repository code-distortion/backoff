<?php

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Settings;

/**
 * Used to calculate delays. Will cache the delays so that the same attempt number will always return the same delay.
 */
class DelayCalculator
{
    /** @var array<integer|float|null> A cache of the base delays that have been calculated. */
    private array $baseDelays = [];

    /** @var array<integer|float|null> A cache of the jittered delays that have been calculated. */
    private array $jitteredDelays = [];



    /**
     * @param BackoffAlgorithmInterface $backoffAlgorithm    The backoff algorithm to use.
     * @param JitterInterface|null      $jitter              The jitter to apply.
     * @param integer|null              $maxAttempts         The maximum number of attempts to allow - null for infinite
     *                                                       (default: null).
     * @param integer|float|null        $maxDelay            The maximum delay to allow.
     * @param string                    $unitType            The unit type to use.
     * @param boolean                   $immediateFirstRetry Whether the first retry should happen immediately, i.e. no
     *                                                       delay.
     * @param boolean                   $delaysEnabled       Whether delays are enabled or not.
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public function __construct(
        private BackoffAlgorithmInterface $backoffAlgorithm,
        private ?JitterInterface $jitter,
        protected ?int $maxAttempts,
        protected int|float|null $maxDelay,
        private string $unitType,
        private bool $immediateFirstRetry,
        private bool $delaysEnabled,
    ) {
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES)) {
            throw BackoffInitialisationException::invalidUnitType($this->unitType);
        }
    }


    /**
     * Reset this instance, so it generates new numbers.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->baseDelays = [];
        $this->jitteredDelays = [];

        return $this;
    }



    /**
     * Get the base delay for a given attempt number.
     *
     * This will cache the result to return consistent results for the same attempt number.
     *
     * @param integer $attemptNumber The attempt that this is a delay for. This calculates the delay that's applied
     *                               before this attempt.
     * @return integer|float|null
     */
    public function getBaseDelay(int $attemptNumber): int|float|null
    {
        if (array_key_exists($attemptNumber, $this->baseDelays)) {
            return $this->baseDelays[$attemptNumber];
        }

        $baseDelay = $this->calculateBaseDelay($attemptNumber);
        $baseDelay = $this->enforceBounds($baseDelay);

        $this->baseDelays[$attemptNumber] = $baseDelay;

        return $baseDelay;
    }

    /**
     * Get the base delay with jitter applied to it for a given attempt number.
     *
     *  This will cache the result to return consistent results for the same attempt number.
     *
     * @param integer $attemptNumber The attempt that this is a delay for. This calculates the delay that's applied
     *                               before this attempt.
     * @return integer|float|null
     */
    public function getJitteredDelay(int $attemptNumber): int|float|null
    {
        if (array_key_exists($attemptNumber, $this->jitteredDelays)) {
            return $this->jitteredDelays[$attemptNumber];
        }

        $baseDelay = $this->getBaseDelay($attemptNumber);
        $jitteredDelay = $this->applyJitter($baseDelay, $attemptNumber);
        $jitteredDelay = $this->enforceMinBound($jitteredDelay);

        $this->jitteredDelays[$attemptNumber] = $jitteredDelay;

        return $jitteredDelay;
    }

    /**
     * Check if a delay exists for a given attempt number.
     *
     * @param integer $attemptNumber The attempt that this is a delay for. This calculates the delay that's applied
     *                               before this attempt.
     * @return boolean
     */
    public function shouldStop(int $attemptNumber): bool
    {
        if ($attemptNumber <= 1) {
            return false;
        }
        return is_null($this->getBaseDelay($attemptNumber));
    }





    /**
     * Get the base delay for a given attempt number.
     *
     * @param integer $attemptNumber The attempt that this is a delay for. This calculates the delay that's applied
     *                               before this attempt.
     * @return integer|float|null
     */
    private function calculateBaseDelay(int $attemptNumber): int|float|null
    {
        // still calculate the delay,
        // this allows us to detect when the Algorithm returns null (to stop)
        $baseDelay = $this->actuallyCalculateBaseDelay($attemptNumber);

        // return null if the Algorithm said to stop (by returning null)
        if (is_null($baseDelay)) {
            return null;
        }

        return $this->delaysEnabled
            ? $baseDelay
            : 0;
    }

    /**
     * Get the base delay for a given attempt number.
     *
     * @param integer $attemptNumber The attempt that this is a delay for. This calculates the delay that's applied
     *                               before this attempt.
     * @return integer|float|null
     */
    private function actuallyCalculateBaseDelay(int $attemptNumber): int|float|null
    {
        if ($attemptNumber <= 1) {
            return null;
        }

        if ((!is_null($this->maxAttempts)) && ($attemptNumber > $this->maxAttempts)) {
            return null;
        }

        $prevBaseDelay = $this->getBaseDelay($attemptNumber - 1);

        if ($this->immediateFirstRetry) {
            if ($attemptNumber == 2) {
                return 0;
            }
            $attemptNumber--;
        }

        // just to be explicit that we're passing the "retry" number instead of the "attempt" number
        $retryNumber = $attemptNumber - 1;

        return $this->backoffAlgorithm->calculateBaseDelay($retryNumber, $prevBaseDelay);
    }

    /**
     * Apply jitter to a delay, if desired.
     *
     * @param integer|float|null $delay         The delay to apply jitter to.
     * @param integer            $attemptNumber The attempt that this is a delay for. This calculates the delay that's
     *                                          applied before this attempt.
     * @return integer|float|null
     */
    private function applyJitter(int|float|null $delay, int $attemptNumber): int|float|null
    {
        if (!$this->backoffAlgorithm->jitterMayBeApplied()) {
            return $delay;
        }

        if (!$this->jitter) {
            return $delay;
        }

        if (is_null($delay)) {
            return null;
        }

        if ($delay <= 0) {
            return $delay;
        }

        // just to be explicit that we're passing the "retry" number instead of the "attempt" number
        $retryNumber = $attemptNumber - 1;

        return $this->jitter->apply($delay, $retryNumber);
    }

    /**
     * Apply the min bound to a delay so the delay isn't below 0 (when specified).
     *
     * @param integer|float|null $delay The delay to apply bounds to.
     * @return integer|float|null
     */
    private function enforceMinBound(int|float|null $delay): int|float|null
    {
        if (is_null($delay)) {
            return null;
        }

        return max(0, $delay);
    }

    /**
     * Apply bounds to a delay so the delay isn't below 0, or above the $maxDelay (when specified).
     *
     * @param integer|float|null $delay The delay to apply bounds to.
     * @return integer|float|null
     */
    private function enforceBounds(int|float|null $delay): int|float|null
    {
        if (is_null($delay)) {
            return null;
        }

        if (!is_null($this->maxDelay)) {
            $delay = min($delay, $this->maxDelay);
        }

        return max(0, $delay);
    }
}
