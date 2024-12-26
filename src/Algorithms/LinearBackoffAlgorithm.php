<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;

/**
 * A class that provides a linear backoff algorithm.
 */
class LinearBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    protected bool $jitterMayBeApplied = true;



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
     * Note: This is intended to run in a stateless way, using only $retryNumber
     * and possibly $prevBaseDelay to work out the next delay.
     *
     * @param integer            $retryNumber   The retry being attempted.
     * @param integer|float|null $prevBaseDelay The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null
    {
        $delayIncrease = $this->delayIncrease ?? $this->initialDelay;

        return $this->initialDelay + (($retryNumber - 1) * $delayIncrease);
    }
}
