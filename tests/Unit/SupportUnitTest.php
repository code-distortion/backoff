<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use DateTime;

/**
 * Test the Support class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class SupportUnitTest extends PHPUnitTestCase
{
    /**
     * Test the generation of floating point numbers.
     *
     * @test
     *
     * @return void
     */
    public static function test_random_float_generation(): void
    {
        for ($count = 0; $count < 100; $count++) {

            $min = mt_rand(-100, 100);
            $max = mt_rand(-100, 100);
            $rand = Support::randFloat($min, $max);

            if ($min > $max) {
                self::assertNull($rand);
            } else {
                self::assertGreaterThanOrEqual($min, $rand);
                self::assertLessThanOrEqual($max, $rand);
            }
        }
    }

    /**
     * Test the number of decimal places a random float has.
     *
     * @test
     *
     * @return void
     */
    public static function test_random_float_dec_pl(): void
    {
        // check the default number of decimal places
        $detectedDecPl = self::howManyDecimalPlacesAreUsed(fn() => Support::randFloat(0, 1));
        self::assertSame(10, $detectedDecPl);

        for ($decPl = 0; $decPl <= 10; $decPl++) {
            // check the default number of decimal places
            $detectedDecPl = self::howManyDecimalPlacesAreUsed(fn() => Support::randFloat(0, 1, $decPl));
            self::assertSame($decPl, $detectedDecPl);
        }
    }

    /**
     * Test the generation of a really large floating point number, that will internally flow over the integer limit.
     *
     * @return void
     */
    public static function test_a_large_random_float(): void
    {
        self::assertNotSame(0, Support::randFloat(1_999_999, 20_000_000_000));
    }

    /**
     * Generate lots of random floats and check how many decimal places they have.
     *
     * @param callable $callback A callback that calls Support::randFloat to generate a random number.
     * @return integer
     */
    public static function howManyDecimalPlacesAreUsed(callable $callback): int
    {
        // generate lots of random floats and check how many decimal places they have
        // (most will have 10 decimal places)
        $decimalPlaceCounts = [];
        for ($count = 0; $count < 1000; $count++) {

            $decPl = self::getDecimalPlaces($callback());

            $decimalPlaceCounts[$decPl] ??= 0;
            $decimalPlaceCounts[$decPl]++;
        }

        asort($decimalPlaceCounts);
        return array_key_last($decimalPlaceCounts);
    }

    /**
     * Check how many decimal places a float has.
     *
     * @param float $number The number to check.
     * @return integer
     */
    private static function getDecimalPlaces(float $number): int
    {
        $exploded = explode('.', (string) $number);

        return count($exploded) === 2
            ? strlen($exploded[1])
            : 0;
    }



    /**
     * Test the conversion of time-spans into different units.
     *
     * @test
     * @dataProvider timespanConversionProvider
     *
     * @param integer|float|null $timespan        The time-span to convert.
     * @param string             $currentUnitType The unit type the time-span is currently in.
     * @param string             $desiredUnitType The unit type the time-span should be converted to.
     * @param integer|float|null $expected        The expected result.
     * @return void
     */
    public static function test_timespan_conversions(
        int|float|null $timespan,
        string $currentUnitType,
        string $desiredUnitType,
        int|float|null $expected,
    ): void {

        self::assertSame($expected, Support::convertTimespan($timespan, $currentUnitType, $desiredUnitType));
    }

    /**
     * DataProvider for test_timespan_conversions.
     *
     * @return array<array<int|float|null|string>>
     */
    public static function timespanConversionProvider(): array
    {
        $return = [];
        foreach ([null, 0.000_001, 0.001, 0, 1, 1000, 1_000_000] as $timespan) {
            foreach (Settings::ALL_UNIT_TYPES as $currentUnitType) {

                // disregard timespans that are too small to matter
                if ($currentUnitType == Settings::UNIT_MICROSECONDS) {
                    if (in_array($timespan, [0.000_001, 0.001], true)) {
                        continue;
                    }
                }
                if ($currentUnitType == Settings::UNIT_MILLISECONDS) {
                    if (in_array($timespan, [0.000_001], true)) {
                        continue;
                    }
                }

                foreach (Settings::ALL_UNIT_TYPES as $desiredUnitType) {

                    $expected = null;
                    if (!is_null($timespan)) {

                        switch ($currentUnitType) {

                            case Settings::UNIT_MICROSECONDS:
                                $multiplier = match ($desiredUnitType) {
                                    Settings::UNIT_MICROSECONDS => 1,
                                    Settings::UNIT_MILLISECONDS => 0.001,
                                    default => 0.000_001, // Settings::UNIT_SECONDS
                                };
                                break;

                            case Settings::UNIT_MILLISECONDS:
                                $multiplier = match ($desiredUnitType) {
                                    Settings::UNIT_MICROSECONDS => 1_000,
                                    Settings::UNIT_MILLISECONDS => 1,
                                    default => 0.001, // Settings::UNIT_SECONDS
                                };
                                break;

                            default: // Settings::UNIT_SECONDS:
                                $multiplier = match ($desiredUnitType) {
                                    Settings::UNIT_MICROSECONDS => 1_000_000,
                                    Settings::UNIT_MILLISECONDS => 1_000,
                                    default => 1, // Settings::UNIT_SECONDS
                                };
                                break;
                        }

                        $expected = $timespan * $multiplier;

                        $expected = ($desiredUnitType == Settings::UNIT_SECONDS)
                            ? $expected
                            : intval(round($expected));

                        if ($desiredUnitType == Settings::UNIT_SECONDS) {
                            if ($expected - floor($expected) == 0) {
                                $expected = (int) $expected;
                            }
                        }
                    }

                    $return[] = [$timespan, $currentUnitType, $desiredUnitType, $expected];
                }
            }
        }

        return $return;
    }

    /**
     * Test that time differences can be calculated.
     *
     * @return void
     */
    public static function test_that_time_differences_can_be_calculated(): void
    {
        self::assertSame(
            0.0,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:00'))
        );

        self::assertSame(
            0.001,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:00.001'))
        );

        self::assertSame(
            0.000_001,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:00.000001'))
        );

        self::assertSame(
            1.0,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:01'))
        );

        self::assertSame(
            1.001,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:01.001'))
        );

        self::assertSame(
            1.000_001,
            Support::timeDiff(new DateTime('2024-01-01 00:00:00'), new DateTime('2024-01-01 00:00:01.000001'))
        );
    }
}
