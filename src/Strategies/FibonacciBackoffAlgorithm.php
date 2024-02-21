<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;

/**
 * A class that provides a Fibonacci backoff strategy.
 */
class FibonacciBackoffStrategy extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this strategy. */
    public bool $jitterMayBeApplied = true;



    /**
     * Constructor
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param boolean       $includeFirst Whether to include the first value in the Fibonacci sequence or not.
     */
    public function __construct(
        private int|float $initialDelay,
        private bool $includeFirst = false,
    ) {
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
        $delay = 0;
        $nextDelay = $this->initialDelay;
        $max = $this->includeFirst
            ? $retryNumber
            : $retryNumber + 1;

        /** @infection-ignore-all $count-- */
        for ($count = 0; $count < $max; $count++) {
            $temp = $nextDelay + $delay;
            $delay = $nextDelay;
            $nextDelay = $temp;
        }

        return $delay;
    }
}
