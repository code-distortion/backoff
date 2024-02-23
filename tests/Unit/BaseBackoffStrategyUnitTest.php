<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
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
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\FixedBackoffWithNoJitterAlgorithm;

/**
 * Test the BaseBackoffStrategy strategy class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BaseBackoffStrategyUnitTest extends PHPUnitTestCase
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
        $strategy = new BackoffStrategy(new FixedBackoffAlgorithm(1));
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        $strategy = new BackoffStrategy(new LinearBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(5)['delay']);

        $strategy = new BackoffStrategy(new ExponentialBackoffAlgorithm(1));
        self::assertSame([1, 2, 4, 8, 16], $strategy->generateTestSequence(5)['delay']);

        $strategy = new BackoffStrategy(new PolynomialBackoffAlgorithm(1));
        self::assertSame([1, 4, 9, 16, 25], $strategy->generateTestSequence(5)['delay']);

        $strategy = new BackoffStrategy(new FibonacciBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 5, 8], $strategy->generateTestSequence(5)['delay']);

        $initialDelay = 1;
        $multiplier = 3;
        $strategy = new BackoffStrategy(new DecorrelatedBackoffAlgorithm($initialDelay, $multiplier));
        foreach ($strategy->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $strategy = new BackoffStrategy(new RandomBackoffAlgorithm($min, $max));
        foreach ($strategy->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        $strategy = new BackoffStrategy(new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]));
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(10)['delay']);

        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;
        $strategy = new BackoffStrategy(new CallbackBackoffAlgorithm($callback));
        self::assertSame([1, 2, 3, 4], $strategy->generateTestSequence(10)['delay']);

        $strategy = new BackoffStrategy(new NoopBackoffAlgorithm());
        self::assertSame([0, 0, 0, 0, 0], $strategy->generateTestSequence(5)['delay']);

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
        $strategy = BackoffStrategy::new($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter allowed, and enabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $strategy = BackoffStrategy::new($algorithm)->fullJitter();
        self::assertNotSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter disallowed, and disabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = BackoffStrategy::new($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)['delay']);

        // jitter disallowed, but enabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = BackoffStrategy::new($algorithm)->fullJitter();
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
        $strategy->performBackoffLogic();
        self::assertNull($strategy->getDelay());



        // check that the strategy doesn't start when the max attempts is 0
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            0, // <<< max attempts
        );
        self::assertSame([], $strategy->generateTestSequence(10)['delay']); // no delays

        // check that the strategy has stopped and won't continue
        $strategy->performBackoffLogic();
        self::assertNull($strategy->getDelay());



        // check that the strategy stops when a callback says to
        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;

        $strategy = new BackoffStrategy(
            new CallbackBackoffAlgorithm($callback),
        );
        self::assertSame([1, 2, 3, 4], $strategy->generateTestSequence(10)['delay']); // 4 delays

        // check that the strategy has stopped and won't continue
        $strategy->performBackoffLogic();
        self::assertNull($strategy->getDelay());



        // check that the strategy stops when the sequence ends
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]),
        );
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

        $strategy->performBackoffLogic();
        self::assertSame(0, $strategy->getDelay());
        $strategy->performBackoffLogic();
        self::assertSame(0, $strategy->getDelay());
        $strategy->performBackoffLogic();
        self::assertSame(0.5, $strategy->getDelay());
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



        // test a number which kills some rounding based mutants
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_001),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $strategy->generateTestSequence(5);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);



        // test a number which kills some rounding based mutants
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_999),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $strategy->generateTestSequence(5);
        self::assertSame([1001, 2002, 3003, 4004, 5005], $delays['delayInMs']);



        // test a number which kills some rounding based mutants
        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm(1.000_000_1),
        );

        $delays = $strategy->generateTestSequence(1);
        self::assertSame([1_000_000], $delays['delayInUs']);



        // test a number which kills some rounding based mutants
        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm(1.000_000_5),
        );

        $delays = $strategy->generateTestSequence(1);
        self::assertSame([1_000_001], $delays['delayInUs']);
    }



    /**
     * Test that the backoff strategy starts with and without the first iteration.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_strategy_can_be_triggered_the_first_iteration(): void
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
     * Test that the backoff strategy returns the current attempt number.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_current_attempt_number_can_be_retrieved(): void
    {
        // when running $strategy->step() at the end of the loop
        $strategy = BackoffStrategy::linear(1)->unitUs()->maxAttempts(5);
        $attemptNumbers = [];
        do {
            $attemptNumbers[] = $strategy->getAttemptNumber();
        } while ($strategy->step());
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);



        // when running $strategy->step() at the beginning of the loop
        $strategy = BackoffStrategy::linear(1)->unitUs()->maxAttempts(5)->runsBeforeFirstAttempt();
        $attemptNumbers = [];
        while ($strategy->step()) {
            $attemptNumbers[] = $strategy->getAttemptNumber();
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

        $strategy = BackoffStrategy::linear(1)->maxAttempts(5);
        do {
            $delays[] = $strategy->getDelay();
            $delaysSeconds[] = $strategy->getDelayInSeconds();
            $delaysMilliseconds[] = $strategy->getDelayInMs();
            $delaysMicroseconds[] = $strategy->getDelayInUs();

            // perform the logic manually to avoid the sleep
        } while ($strategy->performBackoffLogic() && !$strategy->hasStopped());

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);



        // when running $strategy->step() at the beginning of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $strategy = BackoffStrategy::linear(1)->maxAttempts(5)->runsBeforeFirstAttempt();
        // perform the logic manually to avoid the sleep
        while ($strategy->performBackoffLogic() && !$strategy->hasStopped()) {

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

    /**
     * Test that the backoff strategy rounds the delay and jittered delay values it generates when using ms and us.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_delay_and_jittered_delay_have_values_rounded_when_ms_and_us(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1.1]);

        // in seconds
        $strategy = BackoffStrategy::new($algorithm)->noJitter()->unitSeconds();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsFloat($delays['delay'][0]);
        self::assertSame([1.1], $delays['delay']);
        self::assertSame($delays['delay'], $delays['delayInSeconds']);

        // with jitter
        $strategy = BackoffStrategy::new($algorithm)->fullJitter()->unitSeconds();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsFloat($delays['delay'][0]);



        // in milliseconds
        $strategy = BackoffStrategy::new($algorithm)->noJitter()->unitMs();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsInt($delays['delay'][0]);
        self::assertSame([1], $delays['delay']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // with jitter
        $strategy = BackoffStrategy::new($algorithm)->fullJitter()->unitMs();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsInt($delays['delay'][0]);



        // in microseconds
        $strategy = BackoffStrategy::new($algorithm)->noJitter()->unitUs();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsInt($delays['delay'][0]);
        self::assertSame([1], $delays['delay']);
        self::assertSame($delays['delay'], $delays['delayInUs']);

        // with jitter
        $strategy = BackoffStrategy::new($algorithm)->fullJitter()->unitUs();
        $delays = $strategy->generateTestSequence(1);
        self::assertIsInt($delays['delay'][0]);
    }





    /**
     * Check that the backoff strategy's performStepLogic() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_calculate_delay_returns_true_or_false_properly(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            2,
            null,
            null,
            true, // <<< start with the first iteration
        );
        self::assertTrue($strategy->performBackoffLogic()); // is first attempt
        self::assertTrue($strategy->performBackoffLogic()); // is second attempt
        self::assertFalse($strategy->performBackoffLogic()); // too many attempts
        self::assertFalse($strategy->performBackoffLogic()); // already stopped



        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
        );
        self::assertTrue($strategy->performBackoffLogic());
        self::assertFalse($strategy->performBackoffLogic()); // the backoff algorithm chose to stop
    }

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
        $strategy->performBackoffLogic();
        self::assertTrue($strategy->sleep()); // first sleep is 0
        $strategy->performBackoffLogic();
        self::assertTrue($strategy->sleep()); // second sleep is 1
        $strategy->performBackoffLogic();
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
        $strategy = BackoffStrategy::new($algorithm)->maxAttempts(5)->runsBeforeFirstAttempt();
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count == 1, $strategy->isFirstAttempt());
        }

        // when running $strategy->step() at the end of the loop
        $strategy = BackoffStrategy::new($algorithm)->maxAttempts(5);
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
        $strategy = BackoffStrategy::new($algorithm)->maxAttempts(5)->runsBeforeFirstAttempt();
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count == 5, $strategy->isLastAttempt());
        }

        // when running $strategy->step() at the end of the loop
        $strategy = BackoffStrategy::new($algorithm)->maxAttempts(5);
        $count = 0;
        do {
            self::assertSame(++$count == 5, $strategy->isLastAttempt());
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
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
        );
        self::assertFalse($strategy->hasStopped());
        // the first attempt would happen here
        $strategy->performBackoffLogic();
        // the first delay would occur here
        self::assertFalse($strategy->hasStopped());
        // the second attempt would happen here
        $strategy->performBackoffLogic();
        // there are no more delays to occur
        self::assertTrue($strategy->hasStopped()); // has finished


        // check that it should be stopped to begin with when max attempts is 0
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
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
     * Test aspects of the generateTestSequence() method.
     *
     * @test
     *
     * @return void
     */
    public function test_the_strategy_generate_test_sequence_method(): void
    {
        // test edge-cases inside sleep()
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
        );
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
        $strategy->performBackoffLogic();
        $start = microtime(true);
        $strategy->sleep();
        $end = microtime(true);
        self::assertGreaterThanOrEqual(0.01, $end - $start);
    }
}
