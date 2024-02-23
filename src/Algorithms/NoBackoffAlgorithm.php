<?php

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;

/**
 * A class that provides a "no backoff" algorithm - it returns false straight away to stop.
 */
class NoBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    public bool $jitterMayBeApplied = false;



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
        return null;
    }
}
