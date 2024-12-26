<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Jitter;

use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Support\BaseJitter;

/**
 * A class that applies full jitter to delays.
 */
class FullJitter extends BaseJitter implements JitterInterface
{
    /** @var integer|float The jitter starting point, expressed as a percentage of the delay. e.g. 0.75 for 75%. */
    protected int|float $min = 0;

    /** @var integer|float The jitter end point, expressed as a percentage of the delay. e.g. 1.25 for 125%. */
    protected int|float $max = 1;
}
