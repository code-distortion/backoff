<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Interfaces;

/**
 * Interface for classes that provide retry backoff algorithms.
 */
interface BackoffAlgorithmInterface
{
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
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null;

    /**
     * Check if jitter may be applied to the delays produced by this algorithm.
     *
     * @return boolean
     */
    public function jitterMayBeApplied(): bool;
}