<?php

declare(strict_types=1);

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
    protected bool $jitterMayBeApplied = true;

    /** @var list<integer|float> The sequence of delays to use. */
    private array $delays;



    /**
     * Constructor
     *
     * @param array<integer|float|null> $delays The sequence of delays to use.
     * @param boolean                   $repeat Repeat the last delay indefinitely if more retries are needed.
     */
    public function __construct(
        array $delays,
        private bool $repeat = false,
    ) {

        // if null is present in the array, remove it and all delays thereafter
        $index = array_search(null, $delays, true);
        if (is_int($index)) {
            array_splice($delays, $index);
        }

        // ensure the array is indexed from 0
        // so that it's easier for calculateBaseDelay to work with it
        $delays = array_values($delays);

        /** @var list<float|integer> $delays */
        $this->delays = $delays;
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
        // use the delay from the array if it exists
        $index = $retryNumber - 1;
        if (array_key_exists($index, $this->delays)) {
            return $this->delays[$index];
        }

        // repeat the last delay
        if ($this->repeat) {
            return (end($this->delays) !== false)
                ? end($this->delays)
                : null;
        }

        return null;
    }
}
