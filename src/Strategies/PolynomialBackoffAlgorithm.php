<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;

/**
 * A class that provides a polynomial backoff strategy.
 */
class PolynomialBackoffStrategy extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this strategy. */
    public bool $jitterMayBeApplied = true;



    /**
     * Constructor
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param integer|float $power        The power to raise the retry number to (default 2).
     */
    public function __construct(
        private int|float $initialDelay,
        private int|float $power = 2,
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
        return $this->initialDelay * pow($retryNumber, $this->power);
    }
}
