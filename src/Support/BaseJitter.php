<?php

namespace CodeDistortion\Backoff\Support;

/**
 * Abstract jitter class.
 */
abstract class BaseJitter implements JitterInterface
{
    /** @var integer|float The jitter starting point, expressed as a percentage of the delay. e.g. 0.75 for 75%. */
    protected int|float $min = 0;

    /** @var integer|float The jitter end point, expressed as a percentage of the delay. e.g. 1.25 for 125%. */
    protected int|float $max = 1;



    /**
     * Apply jitter to a delay.
     *
     * Note: This is intended to run in a stateless way, only using $delay and its settings to work out the delay.
     *
     * @param integer|float $delay The delay to apply jitter to.
     * @return integer|float
     */
    public function apply(int|float $delay): int|float
    {
        return Support::randFloat(
            $this->min * $delay,
            $this->max * $delay,
        );
    }
}
