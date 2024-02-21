<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\Support;

/**
 * A class that provides a decorrelated backoff algorithm.
 *
 * This algorithm uses the previous delay feedback to influence the next delay.
 */
class DecorrelatedBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    public bool $jitterMayBeApplied = false;



    /**
     * Constructor
     *
     * @param integer|float $baseDelay  The base delay to use.
     * @param integer|float $multiplier The amount to multiply the previous delay by (default 3).
     */
    public function __construct(
        private int|float $baseDelay,
        private int|float $multiplier = 3,
    ) {
    }

    /**
     * Calculate the delay needed before retrying an action.
     *
     * $retryNumber starts at 1 and increases for each subsequent retry.
     *
     * Note: This is intended to run in a stateless way, only using $retryNumber
     * and possibly $prevDelay to work out the next delay.
     *
     * @param integer            $retryNumber The retry being attempted.
     * @param integer|float|null $prevDelay   The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevDelay): int|float|null
    {
        $min = $this->baseDelay;
        $max = ($prevDelay ?? $this->baseDelay) * $this->multiplier;

        return Support::randFloat($min, $max);
    }
}
