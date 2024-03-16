<?php

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;

/**
 * A class that provides a random backoff algorithm.
 *
 * It returns each of the delays from a specified array until exhausted.
 */
class SequenceBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    public bool $jitterMayBeApplied = true;



    /**
     * Constructor
     *
     * @param array<integer|float> $delays       The sequence of delays to use.
     * @param integer|float|null   $continuation The delay to use (when present) after the sequence has been exhausted.
     */
    public function __construct(
        private array $delays,
        private int|float|null $continuation = null,
    ) {
    }

    /**
     * Calculate the delay needed before retrying an action.
     *
     * $retryNumber starts at 1 and increases for each subsequent retry.
     *
     * Note: This is intended to run in a stateless way, using only $retryNumber
     * and possibly $prevDelay to work out the next delay.
     *
     * @param integer            $retryNumber   The retry being attempted.
     * @param integer|float|null $prevBaseDelay The previous delay used (if any).
     * @return integer|float|null
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null
    {
        return $this->delays[$retryNumber - 1] ?? $this->continuation;
    }
}
