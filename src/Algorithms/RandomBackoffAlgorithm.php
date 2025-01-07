<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;
use CodeDistortion\Backoff\Support\Support;

/**
 * A class that provides a random backoff algorithm.
 */
class RandomBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    protected bool $jitterMayBeApplied = false;



    /**
     * Constructor
     *
     * @param integer|float $minDelay The minimum delay to use.
     * @param integer|float $maxDelay The maximum delay to use.
     * @throws BackoffInitialisationException Thrown when the minDelay is greater than the maxDelay.
     */
    public function __construct(
        private int|float $minDelay,
        private int|float $maxDelay,
    ) {
        if ($minDelay > $maxDelay) {
            throw BackoffInitialisationException::randMinIsGreaterThanMax($minDelay, $maxDelay);
        }

        $this->minDelay = max(0, $this->minDelay);
        $this->maxDelay = max(0, $this->maxDelay);
    }

    /**
     * Calculate the delay needed before retrying an action.
     *
     * $retryNumber starts at 1 and increases for each subsequent retry.
     *
     * Note: This is intended to run in a stateless way, using only $retryNumber
     * and possibly $prevBaseDelay to work out the next delay.
     *
     * @param integer            $retryNumber   The retry being attempted.
     * @param integer|float|null $prevBaseDelay The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null
    {
        return Support::randFloat(
            $this->minDelay,
            $this->maxDelay
        );
    }
}
