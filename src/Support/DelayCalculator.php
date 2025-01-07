<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Settings;

/**
 * Used to calculate delays. Will cache the delays so that the same retry number will always return the same delay.
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
     * @param integer|null              $maxRetries          The maximum number of retries to allow - null for infinite
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
        protected ?int $maxRetries,
        protected int|float|null $maxDelay,
        private string $unitType,
        private bool $immediateFirstRetry,
        private bool $delaysEnabled,
    ) {
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES, true)) {
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
     * Get the base delay for a given retry number.
     *
     * This will cache the result, so requests for the same retry return consistent results.
     *
     * @param integer $retryNumber The retry the delay is for.
     * @return integer|float|null
     */
    public function getBaseDelay(int $retryNumber): int|float|null
    {
        if (array_key_exists($retryNumber, $this->baseDelays)) {
            return $this->baseDelays[$retryNumber];
        }

        $baseDelay = $this->calculateBaseDelay($retryNumber);
        $baseDelay = $this->enforceBounds($baseDelay);

        $this->baseDelays[$retryNumber] = $baseDelay;

        return $baseDelay;
    }

    /**
     * Get the delay after jitter is applied to it, for a given retry number.
     *
     * This will cache the result, so requests for the same retry return consistent results.
     *
     * @param integer $retryNumber The retry the delay is for.
     * @return integer|float|null
     */
    public function getJitteredDelay(int $retryNumber): int|float|null
    {
        if (array_key_exists($retryNumber, $this->jitteredDelays)) {
            return $this->jitteredDelays[$retryNumber];
        }

        $baseDelay = $this->getBaseDelay($retryNumber);
        $jitteredDelay = $this->applyJitter($baseDelay, $retryNumber);
        $jitteredDelay = $this->enforceMinBound($jitteredDelay);

        $this->jitteredDelays[$retryNumber] = $jitteredDelay;

        return $jitteredDelay;
    }

    /**
     * Check if a delay will be calculated for a given retry number.
     *
     * @param integer $retryNumber The retry to check.
     * @return boolean
     */
    public function shouldStop(int $retryNumber): bool
    {
        if ($retryNumber <= 0) {
            return false;
        }
        return is_null($this->getBaseDelay($retryNumber));
    }





    /**
     * Get the base delay for a given retry number.
     *
     * @param integer $retryNumber The retry the delay is for.
     * @return integer|float|null
     */
    private function calculateBaseDelay(int $retryNumber): int|float|null
    {
        // still calculate the delay,
        // this allows us to detect when the Algorithm returns null (to stop)
        $baseDelay = $this->actuallyCalculateBaseDelay($retryNumber);

        // return null if the Algorithm said to stop (by returning null)
        if (is_null($baseDelay)) {
            return null;
        }

        // @infection-ignore-all DecrementInteger (the value is bound-checked later so returning -1 is safe)
        return $this->delaysEnabled
            ? $baseDelay
            : 0;
    }

    /**
     * Get the base delay for a given retry number.
     *
     * @param integer $retryNumber The retry the delay is for.
     * @return integer|float|null
     */
    private function actuallyCalculateBaseDelay(int $retryNumber): int|float|null
    {
        if ($retryNumber <= 0) {
            return null;
        }

        // @infection-ignore-all LogicalAndAllSubExprNegation and LogicalNot (timeout, mutant didn't escape)
        if ((!is_null($this->maxRetries)) && ($retryNumber > $this->maxRetries)) {
            return null;
        }

        // @infection-ignore-all change -1 -> +1 and -1 -> -0 (segmentation fault)
        $prevBaseDelay = $this->getBaseDelay($retryNumber - 1);

        if ($this->immediateFirstRetry) {
            if ($retryNumber === 1) {
                // @infection-ignore-all DecrementInteger (the value is bound-checked later so returning -1 is safe)
                return 0;
            }
            $retryNumber--;
        }

        return $this->backoffAlgorithm->calculateBaseDelay($retryNumber, $prevBaseDelay);
    }

    /**
     * Apply jitter to a delay, if desired.
     *
     * @param integer|float|null $delay       The delay to apply jitter to.
     * @param integer            $retryNumber The retry the delay is for.
     * @return integer|float|null
     */
    private function applyJitter(int|float|null $delay, int $retryNumber): int|float|null
    {
        if (!$this->backoffAlgorithm->jitterMayBeApplied()) {
            return $delay;
        }

        if (is_null($this->jitter)) {
            return $delay;
        }

        if (is_null($delay)) {
            return null;
        }

        if ($delay <= 0) {
            return $delay;
        }

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
