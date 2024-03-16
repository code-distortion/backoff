<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\DecorrelatedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\ExponentialBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FibonacciBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\PolynomialBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\RandomBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\FixedBackoffWithNoJitterAlgorithm;

/**
 * Test the BackoffStrategyTrait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyTraitUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the backoff strategy doesn't have any issues using the available backoff algorithms.
     *
     * @test
     *
     * @return void
     */
    public static function test_backoff_strategy_can_use_backoff_algorithms(): void
    {
        // FixedBackoffAlgorithm
        $strategy = new BackoffStrategy(new FixedBackoffAlgorithm(1));
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // LinearBackoffAlgorithm
        $strategy = new BackoffStrategy(new LinearBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(5)['delay']);

        // ExponentialBackoffAlgorithm
        $strategy = new BackoffStrategy(new ExponentialBackoffAlgorithm(1));
        self::assertSame([1, 2, 4, 8, 16], $strategy->generateTestSequence(5)['delay']);

        // PolynomialBackoffAlgorithm
        $strategy = new BackoffStrategy(new PolynomialBackoffAlgorithm(1));
        self::assertSame([1, 4, 9, 16, 25], $strategy->generateTestSequence(5)['delay']);

        // FibonacciBackoffAlgorithm
        $strategy = new BackoffStrategy(new FibonacciBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 5, 8], $strategy->generateTestSequence(5)['delay']);

        // DecorrelatedBackoffAlgorithm
        $initialDelay = 1;
        $multiplier = 3;
        $strategy = new BackoffStrategy(new DecorrelatedBackoffAlgorithm($initialDelay, $multiplier));
        foreach ($strategy->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        // RandomBackoffAlgorithm
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $strategy = new BackoffStrategy(new RandomBackoffAlgorithm($min, $max));
        foreach ($strategy->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        // SequenceBackoffAlgorithm
        $strategy = new BackoffStrategy(new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]));
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(10)['delay']);

        // CallbackBackoffAlgorithm
        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;
        $strategy = new BackoffStrategy(new CallbackBackoffAlgorithm($callback));
        self::assertSame([1, 2, 3, 4], $strategy->generateTestSequence(10)['delay']);

        // NoopBackoffAlgorithm
        $strategy = new BackoffStrategy(new NoopBackoffAlgorithm());
        self::assertSame([0, 0, 0, 0, 0], $strategy->generateTestSequence(5)['delay']);

        // NoBackoffAlgorithm
        $strategy = new BackoffStrategy(new NoBackoffAlgorithm());
        self::assertSame([], $strategy->generateTestSequence(5)['delay']);
    }



    /**
     * Test that the backoff strategy can apply jitter to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_apply_jitter(): void
    {
        $min = 0;
        $max = 1;

        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm($max),
            new FullJitter(), // <<< jitter
        );

        foreach ($strategy->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        $uniqueValues = array_unique($strategy->generateTestSequence(100)['delay']);
        self::assertGreaterThan(50, count($uniqueValues)); // there should be a good spread of values
    }



    /**
     * Test that the delay is passed through to the jitter.
     *
     * @return void
     */
    public static function test_that_delay_is_passed_to_jitter()
    {
        $callback = fn($delay, $retryNumber) => $delay;

        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([5, 2, 4, 6, 3, 1]),
            new CallbackJitter($callback),
        );

        self::assertSame([5, 2, 4, 6, 3, 1], $strategy->generateTestSequence(20)['delay']);
    }



    /**
     * Test that the correct retry number is passed to the jitter, when applying jitter to the delay.
     *
     * @return void
     */
    public static function test_that_retry_number_is_passed_to_jitter()
    {
        $callback = fn($delay, $retryNumber) => $retryNumber;

        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm(1),
            new CallbackJitter($callback),
            10
        );

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $strategy->generateTestSequence(20)['delay']);
    }



    /**
     * Test that backoff strategies can dictate when jitter is allowed to be applied, and that the backoff strategy
     * respects this.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_jitter_is_only_applied_when_allowed_and_desired(): void
    {
        // jitter allowed, but disabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter allowed, and enabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());
        self::assertNotSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter disallowed, and disabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter disallowed, but enabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);
    }



    /**
     * Test that the backoff strategy can apply max attempts to the process.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_apply_max_attempts(): void
    {
        // check that the strategy stops after 5 attempts
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5, // <<< max attempts
        );
        self::assertSame([1, 2, 3, 4], $strategy->generateTestSequence(10)['delay']); // 4 delays

        // check that the strategy has stopped and won't continue
        $strategy->calculate();
        self::assertNull($strategy->getDelay());



        // check that the strategy doesn't start when the max attempts is 0
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            0, // <<< max attempts
        );
        self::assertSame([], $strategy->generateTestSequence(10)['delay']); // no delays

        // check that the strategy has stopped and won't continue
        $strategy->calculate();
        self::assertNull($strategy->getDelay());



        // check that the strategy stops when a callback says to
        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;

        $algorithm = new CallbackBackoffAlgorithm($callback);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 2, 3, 4], $strategy->generateTestSequence(10)['delay']); // 4 delays

        // check that the strategy has stopped and won't continue
        $strategy->calculate();
        self::assertNull($strategy->getDelay());



        // check that the strategy stops when the sequence ends
        $algorithm = new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff strategy can apply max-delay to the process.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_apply_max_delay(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            5, // <<< max-delay
        );
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $strategy->generateTestSequence(10)['delay']);
    }

    /**
     * Test that the backoff strategy applies bounds properly to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_bounds_are_applied_properly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([-1, 0, 1]),
            null,
            null,
            0.5, // <<< max-delay
        );

        $strategy->calculate();
        self::assertSame(0, $strategy->getDelay());
        $strategy->calculate();
        self::assertSame(0, $strategy->getDelay());
        $strategy->calculate();
        self::assertSame(0.5, $strategy->getDelay());
    }





    /**
     * Test the getUnitType() method.
     *
     * @return void
     */
    public static function test_the_get_unit_type_method(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_SECONDS, // <<< unit type
        );
        self::assertSame(Settings::UNIT_SECONDS, $strategy->getUnitType());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< unit type
        );
        self::assertSame(Settings::UNIT_MILLISECONDS, $strategy->getUnitType());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );
        self::assertSame(Settings::UNIT_MICROSECONDS, $strategy->getUnitType());
    }





    /**
     * Test that the backoff strategy can apply unit types to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_apply_unit_types(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_SECONDS, // <<< unit type
        );

        // check the delay reported is null before starting
        self::assertNull($strategy->getDelay());
        self::assertNull($strategy->getDelayInSeconds());
        self::assertNull($strategy->getDelayInMs());
        self::assertNull($strategy->getDelayInUs());

        $delays = $strategy->generateTestSequence(5);
        self::assertSame([1, 2, 3, 4, 5], $delays['delay']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInSeconds']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delayInUs']);



        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< unit type
        );

        $delays = $strategy->generateTestSequence(5);
        self::assertSame([1, 2, 3, 4, 5], $delays['delay']);
        self::assertSame([0.001, 0.002, 0.003, 0.004, 0.005], $delays['delayInSeconds']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInMs']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInUs']);



        // usa a delay of 1_000_000 microseconds so that when converted
        // to milliseconds (which is cast to an int), the outcome can be checked accurately
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_000),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $strategy->generateTestSequence(5);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delay']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInSeconds']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delayInUs']);



//        // test a number which kills some rounding based mutants
//        $strategy = new BackoffStrategy(
//            new LinearBackoffAlgorithm(1_000_001),
//            null,
//            null,
//            null,
//            Settings::UNIT_MICROSECONDS, // <<< unit type
//        );
//
//        $delays = $strategy->generateTestSequence(5);
//        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);



//        // test a number which kills some rounding based mutants
//        $strategy = new BackoffStrategy(
//            new LinearBackoffAlgorithm(1_000_999),
//            null,
//            null,
//            null,
//            Settings::UNIT_MICROSECONDS, // <<< unit type
//        );
//
//        $delays = $strategy->generateTestSequence(5);
//        self::assertSame([1001, 2002, 3003, 4004, 5005], $delays['delayInMs']);



//        // test a number which kills some rounding based mutants
//        $strategy = new BackoffStrategy(
//            new FixedBackoffAlgorithm(1.000_000_1),
//        );
//
//        $delays = $strategy->generateTestSequence(1);
//        self::assertSame([1_000_000], $delays['delayInUs']);



//        // test a number which kills some rounding based mutants
//        $strategy = new BackoffStrategy(
//            new FixedBackoffAlgorithm(1.000_000_5),
//        );
//
//        $delays = $strategy->generateTestSequence(1);
//        self::assertSame([1_000_001], $delays['delayInUs']);
    }



    /**
     * Test that the backoff strategy starts with and without the first iteration.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_be_triggered_at_the_start_of_the_loop(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            true, // <<< start with the first iteration
        );
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $strategy->generateTestSequence(10)['delay']);

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false, // <<< start without the first iteration
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff strategy can insert an immediate first retry.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_insert_an_immediate_first_retry(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(5, 1),
            null,
            null,
            null,
            null,
            true,
            true, // <<< insert an immediate retry
        );
        self::assertSame([null, 0, 5, 6, 7, 8, 9, 10, 11, 12], $strategy->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff strategy disable delays.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_disable_delays(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true, // <<< enable delays
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)['delay']);

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $strategy->generateTestSequence(10)['delay']);

        // make sure it still stops when the backoff algorithm says to
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0], $strategy->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff strategy disables retries.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_disable_retries(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true,
            true, // <<< enable retries
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)['delay']);

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true,
            false, // <<< disable retries
        );
        self::assertSame([], $strategy->generateTestSequence(10)['delay']);
    }



    /**
     * Test Backoff's alternative constructors.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_alternative_constructor(): void
    {
        // new()
        $algorithm = new LinearBackoffAlgorithm(1);
        $backoff = BackoffStrategy::new($algorithm);
        self::assertInstanceOf(BackoffStrategy::class, $backoff);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // with settings
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            null,
            8,
            5,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            true,
            true,
        );
        $delays = $backoff->generateTestSequence(20);
        self::assertSame([null, 0, 1, 2, 3, 4, 5, 5], $delays['delay']);

        // these should be equal given that the unitType was set to milliseconds
        self::assertSame($delays['delayInMs'], $delays['delay']);

        // once more, to check that the FullJitter is applied
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            new FullJitter(),
            8,
            4,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            true,
            true,
        );
        self::assertNotSame([null, 0, 1, 2, 3, 4, 4, 4], $backoff->generateTestSequence(10)['delay']);
    }





    /**
     * Test that the backoff strategy returns the current attempt number.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_current_attempt_number_can_be_retrieved(): void
    {
        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );

        $attemptNumbers = [];
        do {
            $attemptNumbers[] = $strategy->currentAttemptNumber();
        } while ($strategy->step());
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);



        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            true, // <<< run before the first attempt
        );

        $attemptNumbers = [];
        while ($strategy->step()) {
            $attemptNumbers[] = $strategy->currentAttemptNumber();
        }
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);
    }



    /**
     * Test that the backoff strategy returns the previously calculated delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_previously_calculated_delay_can_be_retrieved(): void
    {
        // when running $strategy->step() at the end of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
        );

        do {
            $delays[] = $strategy->getDelay();
            $delaysSeconds[] = $strategy->getDelayInSeconds();
            $delaysMilliseconds[] = $strategy->getDelayInMs();
            $delaysMicroseconds[] = $strategy->getDelayInUs();

            // perform the logic manually to avoid the sleep
        } while ($strategy->calculate() && !$strategy->hasStopped());

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);



        // when running $strategy->step() at the beginning of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            null,
            true,
        );

        // perform the logic manually to avoid the sleep
        while ($strategy->calculate() && !$strategy->hasStopped()) {

            $delays[] = $strategy->getDelay();
            $delaysSeconds[] = $strategy->getDelayInSeconds();
            $delaysMilliseconds[] = $strategy->getDelayInMs();
            $delaysMicroseconds[] = $strategy->getDelayInUs();
        }

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);
    }

//    /**
//     * Test that the backoff strategy rounds the delay and jittered delay values it generates when using ms and us.
//     *
//     * @test
//     *
//     * @return void
//     */
//    public static function test_that_delay_and_jittered_delay_have_values_rounded_when_ms_and_us(): void
//    {
//        $algorithm = new SequenceBackoffAlgorithm([1.1]);
//
//        // in seconds
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            null, // <<< no jitter
//            null,
//            null,
//            Settings::UNIT_SECONDS, // <<< seconds
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//        self::assertSame([1.1], $delays['delay']);
//        self::assertSame($delays['delay'], $delays['delayInSeconds']);
//
//        // with jitter
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            new FullJitter(), // <<< full jitter
//            null,
//            null,
//            Settings::UNIT_SECONDS,
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//
//
//
//        // in milliseconds
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            null, // <<< no jitter
//            null,
//            null,
//            Settings::UNIT_MILLISECONDS, // <<< milliseconds
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//        self::assertSame([1.1], $delays['delay']);
//        self::assertSame($delays['delay'], $delays['delayInMs']);
//
//        // with jitter
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            new FullJitter(), // <<< full jitter
//            null,
//            null,
//            Settings::UNIT_MILLISECONDS, // <<< milliseconds
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//
//
//
//        // in microseconds
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            null, // <<< no jitter
//            null,
//            null,
//            Settings::UNIT_MICROSECONDS, // <<< microseconds
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//        self::assertSame([1.1], $delays['delay']);
//        self::assertSame($delays['delay'], $delays['delayInUs']);
//
//        // with jitter
//        $strategy = new BackoffStrategy(
//            $algorithm,
//            new FullJitter(), // <<< full jitter
//            null,
//            null,
//            Settings::UNIT_MICROSECONDS, // <<< microseconds
//        );
//        $delays = $strategy->generateTestSequence(1);
//        self::assertIsFloat($delays['delay'][0]);
//    }





    /**
     * Check that the backoff strategy's step() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_step_returns_true_or_false_properly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );
        self::assertTrue($strategy->step());
        self::assertFalse($strategy->step());
    }

    /**
     * Check that the backoff strategy's calculate() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_calculate_returns_true_or_false_properly(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            2,
            null,
            null,
            true, // <<< start with the first iteration
        );
        self::assertTrue($strategy->calculate()); // is first attempt
        self::assertTrue($strategy->calculate()); // is second attempt
        self::assertFalse($strategy->calculate()); // too many attempts
        self::assertFalse($strategy->calculate()); // already stopped



        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
        );
        self::assertTrue($strategy->calculate());
        self::assertFalse($strategy->calculate()); // the backoff algorithm chose to stop
    }

    /**
     * Check that the backoff strategy's sleep() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_sleep_returns_true_or_false_properly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([0, 1]),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );
        self::assertTrue($strategy->sleep()); // hasn't started yet
        $strategy->calculate();
        self::assertTrue($strategy->sleep()); // first sleep is 0
        $strategy->calculate();
        self::assertTrue($strategy->sleep()); // second sleep is 1
        $strategy->calculate();
        self::assertFalse($strategy->sleep()); // has finished
    }

    /**
     * Check that the backoff strategy's isFirstAttempt() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_is_first_attempt_returns_true_or_false_properly(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count == 1, $strategy->isFirstAttempt());
        }

        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count == 1, $strategy->isFirstAttempt());
        } while ($strategy->step());
    }

    /**
     * Check that the backoff strategy's isLastAttempt() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_is_last_attempt_returns_true_or_false_properly(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count == 5, $strategy->isLastAttempt());
        }

        // test when stopped = true
        self::assertTrue($strategy->isLastAttempt());



        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count == 5, $strategy->isLastAttempt());
        } while ($strategy->step());





        // when running $strategy->step() at the beginning of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            null, // <<< no max attempts
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 5;
        while ((--$count > 0) && ($strategy->step())) {
            self::assertFalse($strategy->isLastAttempt());
        }



        // when running $strategy->step() at the end of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            null, // <<< no max attempts
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 5;
        do {
            self::assertFalse($strategy->isLastAttempt());
        } while ((--$count > 0) && ($strategy->step()));





        // when running $strategy->step() at the beginning of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2]),
            null,
            null, // <<< no max attempts
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count == 3, $strategy->isLastAttempt());
        }



        // when running $strategy->step() at the end of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2]),
            null,
            null, // <<< no max attempts
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count == 3, $strategy->isLastAttempt());
        } while ($strategy->step());
    }

    /**
     * Check that the backoff strategy's hasStopped() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_has_stopped_returns_true_or_false_properly(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1]);
        $strategy = new BackoffStrategy($algorithm);
        self::assertFalse($strategy->hasStopped());
        // the first attempt would happen here
        $strategy->calculate();
        // the first delay would occur here
        self::assertFalse($strategy->hasStopped());
        // the second attempt would happen here
        $strategy->calculate();
        // there are no more delays to occur
        self::assertTrue($strategy->hasStopped()); // has finished



        // check that it should be stopped to begin with when max attempts is 0
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            0, // <<< max attempts
        );
        self::assertTrue($strategy->hasStopped());
    }

    /**
     * Test that the backoff strategy throws an exception when an invalid unit type is passed.
     *
     * @test
     *
     * @return void
     */
    public function test_that_backoff_strategy_throws_an_exception_due_to_invalid_unit_type(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
            null,
            null,
            null,
            'invalid', // <<< invalid unit type
        );
    }



    /**
     * Test the methods that "start" the backoff strategy.
     *
     * @test
     * @dataProvider methodsThatStartTheStrategyDataProvider
     *
     * @param callable $primeABackoff A function that returns a BackoffStrategy instance.
     * @return void
     */
    public static function test_the_methods_that_start_the_strategy(callable $primeABackoff): void
    {
        $backoff = $primeABackoff();

        $caughtException = false;
        try {
            $backoff->maxAttempts(1); // attempt something that requires the strategy to not have started yet
        } catch (BackoffRuntimeException) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }

    /**
     * Data Provider for test_the_methods_that_start_the_strategy().
     *
     * @return array<callable[]>
     */
    public static function methodsThatStartTheStrategyDataProvider(): array
    {
        // "starting" basically means that the delayCalculator has been created with the desired settings

        // yes these use the Backoff class instead of the BackoffStrategy class which is being tested here,
        // this is because the Backoff has methods like maxAttempts() that trigger an exception when called after
        // "starting". The methods that "start" the strategy are actually in BackoffStrategy however

        return [
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->calculate(); // causes the strategy to "start"
                    return $backoff;
                },
            ],
            [
                function () {
                    $backoff = Backoff::noop()->maxAttempts(0);
                    $backoff->sleep(); // causes the strategy to "start"
                    return $backoff;
                },
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->startOfAttempt(); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->isLastAttempt(); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->getDelay(); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->simulate(1); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->simulateInSeconds(1); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->simulateInMs(1); // causes the strategy to "start"
                    return $backoff;
                }
            ],
            [
                function () {
                    $backoff = Backoff::noop();
                    $backoff->simulateInUs(1); // causes the strategy to "start"
                    return $backoff;
                }
            ],
        ];
    }



    /**
     * Test the things that "start" the backoff process.
     *
     * @test
     * @dataProvider thingsThatStartTheBackoffDataProvider
     *
     * @param callable $callABackoffMethod          A callback to call a method that starts the backoff.
     * @param integer  $expectedNumberOfAttemptLogs Whether the backoff is expected to have started or not.
     * @return void
     */
    public static function test_the_things_that_start_the_backoff(
        callable $callABackoffMethod,
        int $expectedNumberOfAttemptLogs
    ): void {

        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            null,
            true, // <<< run before the first attempt
        );

        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();
        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();
        $backoff->reset();
        // the logs aren't reset until the backoff is "started" again
        self::assertCount(2, $backoff->logs());

        $callABackoffMethod($backoff);

        // will be 0 when backoff "starts" again, because the logs are reset
        self::assertCount($expectedNumberOfAttemptLogs, $backoff->logs());
    }

    /**
     * Data provider for test_the_things_that_start_the_backoff.
     *
     * @return array
     */
    public static function thingsThatStartTheBackoffDataProvider(): array
    {
        // just list every public method here and return:
        // - 2 if it doesn't start the backoff
        // - 0 if it does
        // - 1 for ->startOfAttempt() that starts it, but adds a new one
        return [
            'reset()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->reset(),
                'expectedNumberOfAttemptLogs' => 2,
            ],
            'step()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->step(),
                'expectedNumberOfAttemptLogs' => 0,
            ],
            'calculate()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->calculate(),
                'expectedNumberOfAttemptLogs' => 0,
            ],
            'sleep()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->sleep(),
                'expectedNumberOfAttemptLogs' => 0,
            ],
            'startOfAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->startOfAttempt(),
                'expectedNumberOfAttemptLogs' => 1, // has been reset, but this method adds a new one
            ],
            // ->endOfAttempt() will throw an exception if ->startOfAttempt() isn't called first
//            'endOfAttempt()' => [
//                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->endOfAttempt(),
//                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
//            ],
            'logs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->logs(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'currentLog()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->currentLog(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'hasStopped()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->hasStopped(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'currentAttemptNumber()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->currentAttemptNumber(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'isFirstAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->isFirstAttempt(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'isLastAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->isLastAttempt(),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'getUnitType()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getUnitType(),
                'expectedNumberOfAttemptLogs' => 2, // has been reset, but this method adds a new one
            ],
            'getDelay()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelay(),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'getDelayInSeconds()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInSeconds(),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'getDelayInMs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInMs(),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'getDelayInUs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInUs(),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'simulate()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulate(1),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'simulateInSeconds()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInSeconds(1),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'simulateInMs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInMs(1),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
            'simulateInUs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInUs(1),
                'expectedNumberOfAttemptLogs' => 0, // has been reset, but this method adds a new one
            ],
        ];
    }





    /**
     * Test that the simulate methods generate the correct delays.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_sequence_generates_delays(): void
    {
        $aRange1 = [
            1 => 0.001,
            2 => 0.002,
            3 => 0.004,
            4 => 0.008,
            5 => 0.016,
        ];
        $aRange2 = [
            6 => 0.032,
            7 => 0.064,
            8 => 0.128,
            9 => 0.256,
            10 => 0.512,
        ];
        $bRange1 = [
            1 => 1,
            2 => 2,
            3 => 4,
            4 => 8,
            5 => 16,
        ];
        $bRange2 = [
            6 => 32,
            7 => 64,
            8 => 128,
            9 => 256,
            10 => 512,
        ];
        $cRange1 = [
            1 => 1000,
            2 => 2000,
            3 => 4000,
            4 => 8000,
            5 => 16_000,
        ];
        $cRange2 = [
            6 => 32_000,
            7 => 64_000,
            8 => 128_000,
            9 => 256_000,
            10 => 512_000,
        ];

        // test exponential without jitter
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);

        self::assertSame($bRange2, $strategy->simulate(6, 10));
        self::assertSame($bRange1, $strategy->simulate(1, 5));
        self::assertCount(100, $strategy->simulate(1, 100));

        // test a sequence of delays with int, float and null
        $algorithm = new SequenceBackoffAlgorithm([1, 1.5]);
        $strategy = new BackoffStrategy($algorithm);

        self::assertSame(1, $strategy->simulate(1));
        self::assertSame(1.5, $strategy->simulate(2));
        self::assertSame(null, $strategy->simulate(3));

        self::assertSame(1, $strategy->simulateInSeconds(1));
        self::assertSame(1.5, $strategy->simulateInSeconds(2));
        self::assertSame(null, $strategy->simulateInSeconds(3));

        self::assertSame(1000, $strategy->simulateInMs(1));
        self::assertSame(1500.0, $strategy->simulateInMs(2));
        self::assertSame(null, $strategy->simulateInMs(3));

        self::assertSame(1_000_000, $strategy->simulateInUs(1));
        self::assertSame(1_500_000.0, $strategy->simulateInUs(2));
        self::assertSame(null, $strategy->simulateInUs(3));

        // invalid ranges
        self::assertSame([], $strategy->simulate(0, 20));
        self::assertSame([], $strategy->simulate(0, 0));
        self::assertSame([], $strategy->simulate(-1, 20));
        self::assertSame([], $strategy->simulate(-2, -1));
        self::assertSame([], $strategy->simulate(10, 9));

        // test exponential with jitter
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());

        $delays2a = $strategy->simulate(6, 10);
        $delays1a = $strategy->simulate(1, 5);
        $delays2b = $strategy->simulate(6, 10);
        $delays1b = $strategy->simulate(1, 5);

        self::assertSame($delays1a, $delays1b); // just check they match
        self::assertSame($delays2a, $delays2b);

        // test with a different unit-of-measure
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, null, null, null, Settings::UNIT_MILLISECONDS);

        self::assertSame($bRange2, $strategy->simulate(6, 10));
        self::assertSame($bRange1, $strategy->simulate(1, 5));

        self::assertSame($aRange2, $strategy->simulateInSeconds(6, 10));
        self::assertSame($aRange1, $strategy->simulateInSeconds(1, 5));

        self::assertSame($bRange2, $strategy->simulateInMs(6, 10));
        self::assertSame($bRange1, $strategy->simulateInMs(1, 5));

        self::assertSame($cRange2, $strategy->simulateInUs(6, 10));
        self::assertSame($cRange1, $strategy->simulateInUs(1, 5));
    }





    /**
     * Test aspects of the generateTestSequence() method.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_strategy_generate_test_sequence_method(): void
    {
        // test edge-cases inside sleep()
        $algorithm = new SequenceBackoffAlgorithm([1, 2, 3]);
        $strategy = new BackoffStrategy($algorithm);
        $delays = $strategy->generateTestSequence(10);
        self::assertSame(4, $delays['sleepCallCount']);
        self::assertSame(3, $delays['actualTimesSlept']);

        // test the run-away limit
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([10]),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< ensure the time slept is big enough to detect, but not too big.
        );
        $strategy->calculate();
        $start = microtime(true);
        $strategy->sleep();
        $end = microtime(true);
        self::assertGreaterThanOrEqual(0.01, $end - $start);
    }
}
