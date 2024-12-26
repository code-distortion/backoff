<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Jitter;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Support\BaseJitter;

/**
 * A class that applies a custom jitter range to delays.
 */
class RangeJitter extends BaseJitter implements JitterInterface
{
    /**
     * Constructor
     *
     * @param integer|float $min The jitter starting point, expressed as a percentage of the delay. e.g. 0.75 for 75%.
     * @param integer|float $max The jitter end point, expressed as a percentage of the delay. e.g. 1.25 for 125%.
     * @throws BackoffInitialisationException Thrown when the min is greater than the max.
     */
    public function __construct(int|float $min, int|float $max)
    {
        if ($min > $max) {
            throw BackoffInitialisationException::randMinIsGreaterThanMax($min, $max);
        }
        $this->min = max(0, $min);
        $this->max = max(0, $max);
    }
}
