<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Interfaces;

/**
 * Interface for classes that provides a jitter.
 */
interface JitterInterface
{
    /**
     * Apply jitter to a delay.
     *
     * Note: This is intended to run in a stateless way, only using $delay and its settings to work out the delay.
     *
     * @param integer|float $delay       The delay to apply jitter to.
     * @param integer       $retryNumber The retry being attempted.
     * @return integer|float
     */
    public function apply(int|float $delay, int $retryNumber): int|float;
}
