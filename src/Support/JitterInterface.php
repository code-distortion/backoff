<?php

namespace CodeDistortion\Backoff\Support;

/**
 * Interface for classes that provides a jitter.
 */
interface JitterInterface
{
    /**
     * Apply jitter to a delay.
     *
     * @param integer|float $delay The delay to apply jitter to.
     * @return integer|float
     */
    public function apply(int|float $delay): int|float;
}
