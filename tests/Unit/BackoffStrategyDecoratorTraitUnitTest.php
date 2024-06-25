<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the BackoffStrategyDecoratorTrait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyDecoratorTraitUnitTest extends PHPUnitTestCase
{
    /**
     * Test Backoff's fixed algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_fixed_alternative_constructors(): void
    {
        $expected = [5, 5, 5, 5, 5, 5, 5, 5, 5, 5];
        self::testAltConstBackoff(Backoff::fixed(5), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::fixedMs(5), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::fixedUs(5), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's linear algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_linear_alternative_constructors(): void
    {
        $expected = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];
        self::testAltConstBackoff(Backoff::linear(5), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::linearMs(5), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::linearUs(5), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [5, 15, 25, 35, 45, 55, 65, 75, 85, 95];
        self::testAltConstBackoff(Backoff::linear(5, 10), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::linearMs(5, 10), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::linearUs(5, 10), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's exponential algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_exponential_alternative_constructors(): void
    {
        $expected = [1, 2, 4, 8, 16, 32, 64, 128, 256, 512];
        self::testAltConstBackoff(Backoff::exponential(1), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::exponentialMs(1), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::exponentialUs(1), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [1.0, 1.5, 2.25, 3.375, 5.0625, 7.59375, 11.390625, 17.0859375, 25.62890625, 38.443359375];
        self::testAltConstBackoff(Backoff::exponential(1, 1.5), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::exponentialMs(1, 1.5), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::exponentialUs(1, 1.5), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's polynomial algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_polynomial_alternative_constructors(): void
    {
        $expected = [1, 4, 9, 16, 25, 36, 49, 64, 81, 100];
        self::testAltConstBackoff(Backoff::polynomial(1), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::polynomialMs(1), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::polynomialUs(1), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [
            1.0,
            2.6390158215457884,
            4.655536721746079,
            6.964404506368992,
            9.518269693579391,
            12.286035066475314,
            15.245344971379456,
            18.379173679952558,
            21.674022167526225,
            25.118864315095795,
        ];
        self::testAltConstBackoff(Backoff::polynomial(1, 1.4), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::polynomialMs(1, 1.4), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::polynomialUs(1, 1.4), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's fibonacci algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_fibonacci_alternative_constructors(): void
    {
        $expected = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55];
        self::testAltConstBackoff(Backoff::fibonacci(1), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::fibonacciMs(1), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::fibonacciUs(1), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [1, 2, 3, 5, 8, 13, 21, 34, 55, 89];
        self::testAltConstBackoff(Backoff::fibonacci(1, false), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::fibonacciMs(1, false), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::fibonacciUs(1, false), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55];
        self::testAltConstBackoff(Backoff::fibonacci(1, true), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::fibonacciMs(1, true), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::fibonacciUs(1, true), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's decorrelated algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_decorrelated_alternative_constructors(): void
    {
        $initialDelay = 1;
        $multiplier = 3;

        self::testRandomBasedAltConstBackoff(
            Backoff::decorrelated($initialDelay, $multiplier),
            $initialDelay,
            null,
            Settings::UNIT_SECONDS
        );
        self::testRandomBasedAltConstBackoff(
            Backoff::decorrelatedMs($initialDelay, $multiplier),
            $initialDelay,
            null,
            Settings::UNIT_MILLISECONDS
        );
        self::testRandomBasedAltConstBackoff(
            Backoff::decorrelatedUs($initialDelay, $multiplier),
            $initialDelay,
            null,
            Settings::UNIT_MICROSECONDS
        );
    }

    /**
     * Test Backoff's random algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_random_alternative_constructors(): void
    {
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);

        self::testRandomBasedAltConstBackoff(Backoff::random($min, $max), $min, $max, Settings::UNIT_SECONDS);
        self::testRandomBasedAltConstBackoff(Backoff::randomMs($min, $max), $min, $max, Settings::UNIT_MILLISECONDS);
        self::testRandomBasedAltConstBackoff(Backoff::randomUs($min, $max), $min, $max, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's sequence algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_sequence_alternative_constructors(): void
    {
        $sequence = [9, 8, 7, 6, 5];
        $expected = [9, 8, 7, 6, 5];
        self::testAltConstBackoff(Backoff::sequence($sequence), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::sequenceMs($sequence), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::sequenceUs($sequence), $expected, Settings::UNIT_MICROSECONDS);

        self::testAltConstBackoff(Backoff::sequence($sequence, false), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::sequenceMs($sequence, false), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::sequenceUs($sequence, false), $expected, Settings::UNIT_MICROSECONDS);

        $expected = [9, 8, 7, 6, 5, 5, 5, 5, 5, 5];
        self::testAltConstBackoff(Backoff::sequence($sequence, true), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::sequenceMs($sequence, true), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::sequenceUs($sequence, true), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's callback algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_callback_alternative_constructors(): void
    {
        $callback = fn($retryNumber) => $retryNumber <= 3
            ? $retryNumber * 1000
            : null; // stop after 3 delays
        $expected = [1000, 2000, 3000];
        self::testAltConstBackoff(Backoff::callback($callback), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::callbackMs($callback), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::callbackUs($callback), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's custom algorithm alternative constructors.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_custom_alternative_constructors(): void
    {
        $algorithm = new LinearBackoffAlgorithm(5, 10);
        $expected = [5, 15, 25, 35, 45, 55, 65, 75, 85, 95];
        self::testAltConstBackoff(Backoff::custom($algorithm), $expected, Settings::UNIT_SECONDS);
        self::testAltConstBackoff(Backoff::customMs($algorithm), $expected, Settings::UNIT_MILLISECONDS);
        self::testAltConstBackoff(Backoff::customUs($algorithm), $expected, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Test Backoff's no-op algorithm alternative constructor.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_noop_alternative_constructor(): void
    {
        $delays = Backoff::noop()->generateTestSequence(10);
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $delays->getDelays());
    }

    /**
     * Test Backoff's "none" algorithm alternative constructor.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_none_alternative_constructor(): void
    {
        $delays = Backoff::none()->generateTestSequence(10);
        self::assertSame([], $delays->getDelays());
    }



    /**
     * Test aspects of a Backoff instance that was created by an alternative constructor.
     *
     * @param Backoff                                 $backoff              The Backoff instance to test.
     * @param array<integer|float>                    $expectedWithNoJitter The expected delays when no jitter is
     *                                                                      applied.
     * @param 'seconds'|'milliseconds'|'microseconds' $units                The units the delays are expected to be in.
     * @return void
     */
    private static function testAltConstBackoff(Backoff $backoff, array $expectedWithNoJitter, string $units): void
    {
        $sequenceJitter = $backoff->reset()->generateTestSequence(10);
        $sequenceNoJitter = $backoff->reset()->noJitter()->generateTestSequence(10);

        // check the delays that were generated
        self::assertSame($expectedWithNoJitter, $sequenceNoJitter->getDelays());

        // check that jitter is applied by default (these are very unlikely to be the same)
        self::assertNotSame($sequenceNoJitter->getDelays(), $sequenceJitter->getDelays());

        // check the unit that they're in
        $delaysInCorrectUnit = match ($units) {
            Settings::UNIT_SECONDS => $sequenceJitter->getDelaysInSeconds(),
            Settings::UNIT_MILLISECONDS => $sequenceJitter->getDelaysInMs(),
            Settings::UNIT_MICROSECONDS => $sequenceJitter->getDelaysInUs(),
        };
        self::assertSame($sequenceJitter->getDelays(), $delaysInCorrectUnit);
    }

    /**
     * Test aspects of a Backoff instance that was created by an alternative constructor.
     *
     * @param Backoff                                 $backoff The Backoff instance to test.
     * @param integer|float|null                      $min     The minimum delay to expect.
     * @param integer|float|null                      $max     The maximum delay to expect.
     * @param 'seconds'|'milliseconds'|'microseconds' $units   The units the delays are expected to be in.
     * @return void
     */
    private static function testRandomBasedAltConstBackoff(
        Backoff $backoff,
        int|float|null $min,
        int|float|null $max,
        string $units,
    ): void {

        // just test that it generates numbers in the right range
        // (BackoffAlgorithmUnitTest tests the actual values)
        $sequence = $backoff->reset()->generateTestSequence(100);
        foreach ($sequence->getDelays() as $delay) {
            if (!is_null($min)) {
                self::assertGreaterThanOrEqual($min, $delay);
            }
            if (!is_null($max)) {
                self::assertLessThanOrEqual($max, $delay);
            }
        }

        // check that the delays generated are in the right unit
        $delaysInCorrectUnit = match ($units) {
            Settings::UNIT_SECONDS => $sequence->getDelaysInSeconds(),
            Settings::UNIT_MILLISECONDS => $sequence->getDelaysInMs(),
            Settings::UNIT_MICROSECONDS => $sequence->getDelaysInUs(),
        };
        self::assertSame($delaysInCorrectUnit, $sequence->getDelays());
    }







    /**
     * Test the jitter setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_jitter_setters(): void
    {
        $newBackoff = fn(): Backoff => new Backoff(
            new FixedBackoffAlgorithm(1)
        );
        $callback = fn(int $delay) => mt_rand($delay, $delay + 3);
        $jitter = new RangeJitter(0.75, 1.25);

        self::checkFixedBackoffJitteredRange($newBackoff()->fullJitter(), 0, 1);
        self::checkFixedBackoffJitteredRange($newBackoff()->equalJitter(), 0.5, 1);
        self::checkFixedBackoffJitteredRange($newBackoff()->jitterRange(0.75, 1.25), 0.75, 1.25);
        self::checkFixedBackoffJitteredIsIn($newBackoff()->jitterCallback($callback), [1, 2, 3, 4]);
        self::checkFixedBackoffJitteredRange($newBackoff()->customJitter($jitter), 0.75, 1.25);
        self::checkFixedBackoffJitteredIsIn($newBackoff()->fullJitter()->noJitter(), [1]);
    }

    /**
     * Test that the jittered delays are within the expected range.
     *
     * @param Backoff       $backoff The Backoff instance to test.
     * @param integer|float $min     The minimum delay to expect.
     * @param integer|float $max     The maximum delay to expect.
     * @return void
     */
    private static function checkFixedBackoffJitteredRange(Backoff $backoff, int|float $min, int|float $max): void
    {
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }
    }

    /**
     * Test that the jittered delays match a set of specific values.
     *
     * @param Backoff              $backoff        The Backoff instance to test.
     * @param array<integer|float> $possibleDelays The allowed delays that can be generated.
     * @return void
     */
    private static function checkFixedBackoffJitteredIsIn(Backoff $backoff, array $possibleDelays): void
    {
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertContains($delay, $possibleDelays);
        }
    }







    /**
     * Test the max-attempt setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_max_attempt_setters(): void
    {
        $newBackoff = fn(?int $initialMaxAttempts): Backoff => Backoff::new(
            new NoopBackoffAlgorithm(),
            null,
            $initialMaxAttempts,
        );

        $rand = mt_rand(5, 10); // just some number larger than 1

        // test that ->maxAttempts(), ->noMaxAttempts and ->noAttemptLimit() set the maxAttempts correctly
        // also tests that the "stopped" flag is reassessed upon calling them, by having different initial values
        foreach ([null, -1, 0, 1, $rand] as $initialAttempts) {
            foreach ([null, -1, 0, 1, $rand] as $maxAttempts) {

                $expected = is_int($maxAttempts)
                    ? max(0, $maxAttempts)
                    : $maxAttempts;

                self::checkTheNumberOfAttempts($newBackoff($initialAttempts)->maxAttempts($maxAttempts), $expected);
                if (is_null($maxAttempts)) { // then removing the limit, also include these which do the same thing
                    self::checkTheNumberOfAttempts($newBackoff($initialAttempts)->noMaxAttempts(), $expected);
                    self::checkTheNumberOfAttempts($newBackoff($initialAttempts)->noAttemptLimit(), $expected);
                }
            }
        }
    }

    /**
     * Check the number of delays that are generated.
     *
     * @param Backoff      $backoff              The Backoff instance to test.
     * @param integer|null $expectedAttemptCount The number of delays that are expected to be generated (null for
     *                                           infinite).
     * @return void
     */
    private static function checkTheNumberOfAttempts(Backoff $backoff, ?int $expectedAttemptCount): void
    {
        $temp = ((int) $expectedAttemptCount);
        $maxSteps = mt_rand($temp + 10, $temp + 20); // pick a number that's larger
        $expectedAttemptCount ??= $maxSteps; // if no expected count is given, use the overall max

        $count = 0;
        $backoff->runsAtStartOfLoop();
        while (($backoff->step()) && ($count++ < $maxSteps)) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount($expectedAttemptCount, $backoff->logs());
    }







    /**
     * Test the max-delay setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_max_delay_setters(): void
    {
        $newBackoff = fn(): Backoff => Backoff::new(
            new LinearBackoffAlgorithm(1),
        );

        self::checkTheMaxDelay($newBackoff()->maxDelay(-1), 10, 0);
        self::checkTheMaxDelay($newBackoff()->maxDelay(0), 10, 0);
        self::checkTheMaxDelay($newBackoff()->maxDelay(5), 10, 5);
        self::checkTheMaxDelay($newBackoff()->noMaxDelay(), 10, 10);
        self::checkTheMaxDelay($newBackoff()->noDelayLimit(), 10, 10);
    }

    /**
     * Test that the delays are within the expected range.
     *
     * @param Backoff            $backoff          The Backoff instance to test.
     * @param integer            $attempts         The number of attempts to generate.
     * @param integer|float|null $maxExpectedDelay The maximum delay to expect.
     * @return void
     */
    private static function checkTheMaxDelay(Backoff $backoff, int $attempts, int|float|null $maxExpectedDelay): void
    {
        foreach ($backoff->generateTestSequence($attempts)->getDelays() as $delay) {
            self::assertLessThanOrEqual($maxExpectedDelay, $delay);
        }
    }







    /**
     * Test the unit-of-measure setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_unit_of_measure_setters(): void
    {
        // unit - Settings::UNIT_SECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_SECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());

        // unit - Settings::UNIT_MILLISECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MILLISECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());

        // unit - Settings::UNIT_MICROSECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MICROSECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());

        // seconds
        $backoff = Backoff::linear(1)->unitSeconds();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());

        // milliseconds
        $backoff = Backoff::linear(1)->unitMs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());

        // microseconds
        $backoff = Backoff::linear(1)->unitUs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
    }







    /**
     * Test the runs-before-first-attempt setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_runs_before_first_attempt_setters(): void
    {
        $newBackoff = fn(): Backoff => Backoff::new(
            new LinearBackoffAlgorithm(1)
        )->runsAtStartOfLoop();

        // include the initial attempt
        self::checkSequence($newBackoff()->runsAtStartOfLoop(), [null, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
        self::checkSequence($newBackoff()->runsAtStartOfLoop(true), [null, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        // don't include the initial attempt
        self::checkSequence($newBackoff()->runsAtStartOfLoop(false), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        self::checkSequence($newBackoff()->runsAtEndOfLoop(), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    }







    /**
     * Test the immediate-first-retry setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_immediate_first_retry_setters(): void
    {
        $newBackoff = fn(): Backoff => Backoff::new(
            new LinearBackoffAlgorithm(1)
        )->immediateFirstRetry();

        // insert a 0 delay
        self::checkSequence($newBackoff()->immediateFirstRetry(), [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
        self::checkSequence($newBackoff()->immediateFirstRetry(true), [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        // don't insert a 0 delay
        self::checkSequence($newBackoff()->immediateFirstRetry(false), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        self::checkSequence($newBackoff()->noImmediateFirstRetry(), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    }







    /**
     * Test the only-delay-when setter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_only_delay_when_setter(): void
    {
        $newBackoff = fn(): Backoff => Backoff::new(
            new LinearBackoffAlgorithm(1)
        );

        // enable the delay
        self::checkSequence($newBackoff()->onlyDelayWhen(true), [1, 2, 3, 4, 5], 5);

        // disable the delay
        self::checkSequence($newBackoff()->onlyDelayWhen(false), [0, 0, 0, 0, 0], 5);

        // disable then re-enable the delay
        self::checkSequence($newBackoff()->onlyDelayWhen(false)->onlyDelayWhen(true), [1, 2, 3, 4, 5], 5);
    }







    /**
     * Test the only-retry-when setter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_only_retry_when_setter(): void
    {
        $newBackoff = fn(): Backoff => Backoff::new(
            new LinearBackoffAlgorithm(1)
        );

        // enable retries
        self::checkSequence($newBackoff()->onlyRetryWhen(true), [1, 2, 3, 4, 5], 5);

        // disable retries
        self::checkSequence($newBackoff()->onlyRetryWhen(false), [], 5);

        // disable then re-enable retries
        self::checkSequence($newBackoff()->onlyRetryWhen(false)->onlyRetryWhen(true), [1, 2, 3, 4, 5], 5);
    }







    /**
     * Test that Backoff throws exceptions when settings change after starting.
     *
     * @test
     * @dataProvider updateBackoffCallbackProvider
     *
     * @param callable $updateBackoffCallback The callback to update the backoff.
     * @return void
     */
    #[Test]
    #[DataProvider('updateBackoffCallbackProvider')]
    public function test_that_exceptions_are_thrown_when_settings_change_after_starting(
        callable $updateBackoffCallback
    ): void {

        $this->expectException(BackoffRuntimeException::class);

        $backoff = Backoff::new(
            new NoopBackoffAlgorithm()
        );

        $backoff->step();
        $updateBackoffCallback($backoff);
    }

    /**
     * DataProvider for test_that_exceptions_are_thrown_after_starting.
     *
     * @return array<array<callable>>
     */
    public static function updateBackoffCallbackProvider(): array
    {
        return [
            [fn(Backoff $backoff) => $backoff->fullJitter()],
            [fn(Backoff $backoff) => $backoff->equalJitter()],
            [fn(Backoff $backoff) => $backoff->jitterRange(0, 1)],
            [fn(Backoff $backoff) => $backoff->jitterCallback(fn(int $delay) => $delay)],
            [fn(Backoff $backoff) => $backoff->customJitter(new FullJitter())],
            [fn(Backoff $backoff) => $backoff->noJitter()],

            [fn(Backoff $backoff) => $backoff->maxAttempts(4)],
            [fn(Backoff $backoff) => $backoff->noMaxAttempts()],
            [fn(Backoff $backoff) => $backoff->noAttemptLimit()],

            [fn(Backoff $backoff) => $backoff->maxDelay(100)],
            [fn(Backoff $backoff) => $backoff->noMaxDelay()],
            [fn(Backoff $backoff) => $backoff->noDelayLimit()],

            [fn(Backoff $backoff) => $backoff->unit(Settings::UNIT_SECONDS)],
            [fn(Backoff $backoff) => $backoff->unitSeconds()],
            [fn(Backoff $backoff) => $backoff->unitMs()],
            [fn(Backoff $backoff) => $backoff->unitUs()],

            [fn(Backoff $backoff) => $backoff->runsAtStartOfLoop()],
            [fn(Backoff $backoff) => $backoff->runsAtEndOfLoop()],

            [fn(Backoff $backoff) => $backoff->immediateFirstRetry()],
            [fn(Backoff $backoff) => $backoff->noImmediateFirstRetry()],

            [fn(Backoff $backoff) => $backoff->onlyDelayWhen(true)],
            [fn(Backoff $backoff) => $backoff->onlyRetryWhen(true)],
        ];
    }







    /**
     * Check the sequence generated by a Backoff instance.
     *
     * @param Backoff                   $backoff        The Backoff instance to check.
     * @param array<integer|float|null> $expectedDelays The expected delays to be generated.
     * @param integer                   $attemptCount   The number of attempts to generate.
     * @return void
     */
    private static function checkSequence(Backoff $backoff, array $expectedDelays, int $attemptCount = 10): void
    {
        self::assertSame($expectedDelays, $backoff->generateTestSequence($attemptCount)->getDelays());
    }
}
