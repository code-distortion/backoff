<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\CounterClass;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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
    #[Test]
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
    #[Test]
    public static function test_random_float_dec_pl(): void
    {
        // check the default number of decimal places
        $detectedDecPl = self::howManyDecimalPlacesAreUsed(fn() => (float) Support::randFloat(0, 1));
        self::assertSame(10, $detectedDecPl);

        for ($decPl = 0; $decPl <= 10; $decPl++) {
            // check the default number of decimal places
            $detectedDecPl = self::howManyDecimalPlacesAreUsed(fn() => (float) Support::randFloat(0, 1, $decPl));
            self::assertSame($decPl, $detectedDecPl);
        }
    }

    /**
     * Test the generation of a really large floating point number, that will internally flow over the integer limit.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_a_large_random_float(): void
    {
        self::assertNotSame(0, Support::randFloat(1_999_999, 20_000_000_000));
    }

    /**
     * Generate lots of random floats and check how many decimal places they have.
     *
     * @param callable():float $callback A callback that calls Support::randFloat to generate a random number.
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
        return (int) array_key_last($decimalPlaceCounts);
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
    #[Test]
    #[DataProvider('timespanConversionProvider')]
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
        foreach ([null, 0.000_001, 0.001, 0.0, 1.0, 1000.0, 1_000_000.0] as $timespan) {
            foreach (Settings::ALL_UNIT_TYPES as $currentUnitType) {

                // disregard timespans that are too small to matter
                if ($currentUnitType === Settings::UNIT_MICROSECONDS) {
                    if (in_array($timespan, [0.000_001, 0.001], true)) {
                        continue;
                    }
                } elseif ($currentUnitType === Settings::UNIT_MILLISECONDS) {
                    if (in_array($timespan, [0.000_001], true)) {
                        continue;
                    }
                }

                foreach (Settings::ALL_UNIT_TYPES as $desiredUnitType) {

                    $expected = null;
                    if (!is_null($timespan)) {

                        $multiplier = match ($currentUnitType) {
                            Settings::UNIT_MICROSECONDS => match ($desiredUnitType) {
                                Settings::UNIT_MICROSECONDS => 1.0,
                                Settings::UNIT_MILLISECONDS => 0.001,
                                default => 0.000_001,
                            },
                            Settings::UNIT_MILLISECONDS => match ($desiredUnitType) {
                                Settings::UNIT_MICROSECONDS => 1_000,
                                Settings::UNIT_MILLISECONDS => 1.0,
                                default => 0.001,
                            },
                            default => match ($desiredUnitType) {
                                Settings::UNIT_MICROSECONDS => 1_000_000,
                                Settings::UNIT_MILLISECONDS => 1_000,
                                default => 1.0,
                            },
                        };

                        $expected = $timespan * $multiplier;
                    }

                    $return[] = [$timespan, $currentUnitType, $desiredUnitType, $expected];
                }
            }
        }

        return $return;
    }



    /**
     * Test that the convertTimespan() method returns nulls when necessary.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_convert_timespan_nulls(): void
    {
        $rand = mt_rand(0, 10000);

        $us = Settings::UNIT_MICROSECONDS;
        $ms = Settings::UNIT_MILLISECONDS;
        $sec = Settings::UNIT_SECONDS;

        foreach ([$us, $ms, $sec] as $unit) {

            self::assertSame(null, Support::convertTimespan(null, $unit, $unit));
            self::assertSame(0, Support::convertTimespan(0, $unit, $unit));
            self::assertSame($rand, Support::convertTimespan($rand, $unit, $unit));

            self::assertSame(null, Support::convertTimespan(null, 'something-invalid', $unit));
            self::assertSame(null, Support::convertTimespan(0, 'something-invalid', $unit));
            self::assertSame(null, Support::convertTimespan($rand, 'something-invalid', $unit));

            self::assertSame(null, Support::convertTimespan(null, $unit, 'something-invalid'));
            self::assertSame(null, Support::convertTimespan(0, $unit, 'something-invalid'));
            self::assertSame(null, Support::convertTimespan($rand, $unit, 'something-invalid'));
        }
    }

    /**
     * Test that the convertTimespanAsNumber() method returns 0 when necessary.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_convert_timespan_as_number_nulls(): void
    {
        $rand = mt_rand(0, 10000);

        $us = Settings::UNIT_MICROSECONDS;
        $ms = Settings::UNIT_MILLISECONDS;
        $sec = Settings::UNIT_SECONDS;

        foreach ([$us, $ms, $sec] as $unit) {

            self::assertSame(0, Support::convertTimespanAsNumber(null, $unit, $unit));
            self::assertSame(0, Support::convertTimespanAsNumber(0, $unit, $unit));
            self::assertSame($rand, Support::convertTimespanAsNumber($rand, $unit, $unit));

            self::assertSame(0, Support::convertTimespanAsNumber(null, 'something-invalid', $unit));
            self::assertSame(0, Support::convertTimespanAsNumber(0, 'something-invalid', $unit));
            self::assertSame(0, Support::convertTimespanAsNumber($rand, 'something-invalid', $unit));

            self::assertSame(0, Support::convertTimespanAsNumber(null, $unit, 'something-invalid'));
            self::assertSame(0, Support::convertTimespanAsNumber(0, $unit, 'something-invalid'));
            self::assertSame(0, Support::convertTimespanAsNumber($rand, $unit, 'something-invalid'));
        }
    }


    /**
     * Test that time differences can be calculated.
     *
     * @test
     *
     * @return void
     */
    #[Test]
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



    /**
     * Test the normalisation of parameters - that the parameters are combined as an array.
     *
     * @test
     * @dataProvider normalisedParametersDataProvider
     *
     * @param mixed[] $parameters       The parameters to normalise.
     * @param boolean $checkForCallable When detecting arrays to flatten, whether to check for arrays that are callable.
     * @param mixed[] $expected         The expected result.
     * @return void
     */
    #[Test]
    #[DataProvider('normalisedParametersDataProvider')]
    public static function test_the_normalisation_of_parameters(
        array $parameters,
        bool $checkForCallable,
        array $expected,
    ): void {

        $result = Support::normaliseParameters($parameters, $checkForCallable);

        self::assertSame($expected, $result);
    }

    /**
     * DataProvider for test_the_normalisation_of_parameters.
     *
     * @return array<array<string,mixed[]|boolean>>
     */
    public static function normalisedParametersDataProvider(): array
    {
        $closure1 = fn() => true;
        $closure2 = fn() => true;

        $counter = new CounterClass();
        $callable1 = [$counter, 'increment'];

        return [

            // different combinations of arrays
            [
                'parameters' => [],
                'checkForCallable' => false,
                'expected' => [],
            ],
            [
                'parameters' => [1, 2, 3],
                'checkForCallable' => false,
                'expected' => [1, 2, 3],
            ],
            [
                'parameters' => [[1, 2], 3],
                'checkForCallable' => false,
                'expected' => [1, 2, 3],
            ],
            [
                'parameters' => [[1], [2], [3]],
                'checkForCallable' => false,
                'expected' => [1, 2, 3],
            ],
            [
                'parameters' => [1, ['2a', '2b'], [[3]]],
                'checkForCallable' => false,
                'expected' => [1, '2a', '2b', [3]],
            ],
            [
                'parameters' => [$closure1],
                'checkForCallable' => false,
                'expected' => [$closure1],
            ],



            // closures
            [
                'parameters' => [$closure1, [$closure2]],
                'checkForCallable' => false,
                'expected' => [$closure1, $closure2],
            ],
            [
                'parameters' => [$closure1, [$closure2]],
                'checkForCallable' => true,
                'expected' => [$closure1, $closure2],
            ],



            // [object, method] array
            [
                'parameters' => [$closure1, $callable1],
                'checkForCallable' => false,
                'expected' => [$closure1, $counter, 'increment'],
            ],
            [
                'parameters' => [$closure1, $callable1],
                'checkForCallable' => true,
                'expected' => [$closure1, $callable1],
            ],
        ];
    }



    /**
     * Test that the normaliseParameters() method doesn't check for callables by default.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_normalise_parameters_method_doesnt_check_for_callables_by_default(): void
    {
        $counter = new CounterClass();
        $callable = [$counter, 'increment'];

        $result = Support::normaliseParameters([$callable]);
        self::assertSame($callable, $result);
    }
}
