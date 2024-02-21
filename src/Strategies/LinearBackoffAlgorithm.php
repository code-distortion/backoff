<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;

/**
 * A class that provides a linear backoff strategy.
 */
class LinearBackoffStrategy extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this strategy. */
    public bool $jitterMayBeApplied = true;



    /**
     * Constructor
     *
     * @param integer|float      $initialDelay  The initial delay to use.
     * @param integer|float|null $delayIncrease The amount to increase the delay by (optional, falls back to
     *                                          $initialDelay).
     */
    public function __construct(
        private int|float $initialDelay,
        private int|float|null $delayIncrease = null,
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
        $delayIncrease = $this->delayIncrease ?? $this->initialDelay;

        return $this->initialDelay + (($retryNumber - 1) * $delayIncrease);
    }
}
