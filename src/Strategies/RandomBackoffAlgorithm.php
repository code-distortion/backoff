<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;
use CodeDistortion\Backoff\Support\Support;

/**
 * A class that provides a random backoff strategy.
 */
class RandomBackoffStrategy extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this strategy. */
    public bool $jitterMayBeApplied = false;



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
     * Note: This is intended to run in a stateless way, only using $retryNumber
     * and possibly $prevDelay to work out the delay.
     *
     * @param integer            $retryNumber The retry being attempted.
     * @param integer|float|null $prevDelay   The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBackoffDelay(int $retryNumber, int|float|null $prevDelay): int|float|null
    {
        return Support::randFloat(
            $this->minDelay,
            $this->maxDelay
        );
    }
}
