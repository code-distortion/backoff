<?php

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;

/**
 * A class that provides a polynomial backoff algorithm.
 */
class PolynomialBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
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
     * and possibly $prevDelay to work out the next delay.
     *
     * @param integer            $retryNumber The retry being attempted.
     * @param integer|float|null $prevDelay   The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevDelay): int|float|null
    {
        return $this->initialDelay * pow($retryNumber, $this->power);
    }
}
