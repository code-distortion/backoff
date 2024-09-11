<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Exceptions;

/**
 * The Backoff class for initialisation exceptions.
 */
class BackoffInitialisationException extends BackoffException
{
    /**
     * A min value was given that was greater than the max value.
     *
     * @param integer|float $minDelay The min value given.
     * @param integer|float $maxDelay The max value given.
     * @return self
     */
    public static function randMinIsGreaterThanMax(int|float $minDelay, int|float $maxDelay): self
    {
        return new self("A min value ($minDelay) was given that is greater than the max value ($maxDelay)");
    }

    /**
     * When an invalid unit type was given.
     *
     * @param string $unitType The invalid unit type given.
     * @return self
     */
    public static function invalidUnitType(string $unitType): self
    {
        return new self("Invalid unit type \"$unitType\" was given");
    }
}
