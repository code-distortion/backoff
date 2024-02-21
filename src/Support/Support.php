<?php

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\Settings;
use DateTime;

/**
 * General methods used by the backoff classes.
 */
abstract class Support
{
    /**
     * Generate a random float between $min and $max.
     *
     * @param integer|float $min   The minimum possible value.
     * @param integer|float $max   The maximum possible value.
     * @param integer       $decPl The number of decimal places to use.
     * @return float|null
     */
    public static function randFloat(int|float $min, int|float $max, int $decPl = 10): ?float
    {
        if ($min > $max) {
            return null;
        }

        $multiplier = pow(10, $decPl);
        $minInt = intval($min * $multiplier);
        $maxInt = intval($max * $multiplier);

        // safety net just in case this number gets too large
        if ($maxInt < $minInt) {
            $maxInt = PHP_INT_MAX;
        }

        $randInt = mt_rand($minInt, $maxInt);
        return $randInt / $multiplier;
    }

    /**
     * Convert a delay into a different unit type.
     *
     * @param integer|float|null $delay           The delay to convert.
     * @param string             $currentUnitType The unit type the delay is currently in.
     * @param string             $desiredUnitType The unit type to convert the delay to.
     * @return integer|float|null
     */
    public static function convertTimespan(
        int|float|null $delay,
        string $currentUnitType,
        string $desiredUnitType,
    ): int|float|null {

        if (is_null($delay)) {
            return null;
        }

        switch ($desiredUnitType) {

            case Settings::UNIT_MICROSECONDS:
                $microseconds = match ($currentUnitType) {
                    Settings::UNIT_MICROSECONDS => $delay,
                    Settings::UNIT_MILLISECONDS => $delay * 1_000,
                    default => $delay * 1_000_000,
                };
                // milliseconds are whole numbers - int
                return intval(round($microseconds));

            case Settings::UNIT_MILLISECONDS:
                $milliseconds = match ($currentUnitType) {
                    Settings::UNIT_MICROSECONDS => $delay / 1_000,
                    Settings::UNIT_MILLISECONDS => $delay,
                    default => $delay * 1_000,
                };
                // milliseconds are whole numbers - int
                return intval(round($milliseconds));

            default: // Settings::UNIT_SECONDS
                return match ($currentUnitType) {
                    Settings::UNIT_MICROSECONDS => $delay / 1_000_000,
                    Settings::UNIT_MILLISECONDS => $delay / 1_000,
                    default => $delay,
                };
        }
    }


    /**
     * Calculate the difference between two DateTime objects in seconds (with partial seconds).
     *
     * @param DateTime $start The start time.
     * @param DateTime $end   The end time.
     * @return float
     */
    public static function timeDiff(DateTime $start, DateTime $end): float
    {
        // calculate difference in seconds
        $diffInSeconds = $end->getTimestamp() - $start->getTimestamp();

        // calculate difference in microseconds
        $microseconds1 = intval($start->format('u'));
        $microseconds2 = intval($end->format('u'));
        $diffInUs = $microseconds2 - $microseconds1;

        // convert difference in microseconds to seconds and add to total seconds difference
        return $diffInSeconds + ($diffInUs / 1000000);
    }

    /**
     * Take the parameters and return them as an array.
     *
     * todo - test this method
     *
     * @param mixed[] $parameters       The parameters to normalise.
     * @param boolean $checkForCallable When detecting arrays to flatten, whether to check for an array that's callable.
     * @return array
     */
    public static function normaliseParameters(array $parameters, bool $checkForCallable = false): array
    {
        $returnArgs = [];
        foreach ($parameters as $parameter) {

            $isArray = is_array($parameter) && (!$checkForCallable || !is_callable($parameter));

            $returnArgs = array_merge(
                $returnArgs,
                $isArray ? $parameter : [$parameter]
            );
        }

        return $returnArgs;
    }
}
