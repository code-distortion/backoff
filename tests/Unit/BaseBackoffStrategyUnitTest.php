<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Strategies\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\DecorrelatedBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\ExponentialBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\FibonacciBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\NoBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\PolynomialBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\RandomBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\FixedBackoffWithNoJitterAlgorithm;

/**
 * Test the AbstractBackoffHandler handler class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BaseBackoffHandlerUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the backoff handler doesn't have any issues using the available backoff strategies.
     *
     * @test
     *
     * @return void
     */
    public static function test_backoff_handler_can_use_backoff_strategies(): void
    {
        $handler = new BackoffStrategy(new FixedBackoffAlgorithm(1));
        self::assertSame([1, 1, 1, 1, 1], $handler->generateTestSequence(5)['delay']);

        $handler = new BackoffStrategy(new LinearBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 4, 5], $handler->generateTestSequence(5)['delay']);

        $handler = new BackoffStrategy(new ExponentialBackoffAlgorithm(1));
        self::assertSame([1, 2, 4, 8, 16], $handler->generateTestSequence(5)['delay']);

        $handler = new BackoffStrategy(new PolynomialBackoffAlgorithm(1));
        self::assertSame([1, 4, 9, 16, 25], $handler->generateTestSequence(5)['delay']);

        $handler = new BackoffStrategy(new FibonacciBackoffAlgorithm(1));
        self::assertSame([1, 2, 3, 5, 8], $handler->generateTestSequence(5)['delay']);

        $initialDelay = 1;
        $multiplier = 3;
        $handler = new BackoffStrategy(new DecorrelatedBackoffAlgorithm($initialDelay, $multiplier));
        foreach ($handler->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $handler = new BackoffStrategy(new RandomBackoffAlgorithm($min, $max));
        foreach ($handler->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        $handler = new BackoffStrategy(new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]));
        self::assertSame([1, 2, 3, 4, 5], $handler->generateTestSequence(10)['delay']);

        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;
        $handler = new BackoffStrategy(new CallbackBackoffAlgorithm($callback));
        self::assertSame([1, 2, 3, 4], $handler->generateTestSequence(10)['delay']);

        $handler = new BackoffStrategy(new NoopBackoffAlgorithm());
        self::assertSame([0, 0, 0, 0, 0], $handler->generateTestSequence(5)['delay']);

        $handler = new BackoffStrategy(new NoBackoffAlgorithm());
        self::assertSame([], $handler->generateTestSequence(5)['delay']);
    }



    /**
     * Test that the backoff handler can apply jitter to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_apply_jitter(): void
    {
        $min = 0;
        $max = 1;

        $handler = new BackoffStrategy(
            new FixedBackoffAlgorithm($max),
            new FullJitter(), // <<< jitter
        );

        foreach ($handler->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        $uniqueValues = array_unique($handler->generateTestSequence(100)['delay']);
        self::assertGreaterThan(50, count($uniqueValues)); // there should be a good spread of values
    }



    /**
     * Test that backoff strategies can dictate when jitter is allowed to be applied, and that the backoff handler
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
        $backoff = BackoffStrategy::custom($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $backoff->generateTestSequence(5)['delay']);

        // jitter allowed, and enabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $backoff = BackoffStrategy::custom($algorithm)->fullJitter();
        self::assertNotSame([1, 1, 1, 1, 1], $backoff->generateTestSequence(5)['delay']);

        // jitter disallowed, and disabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $backoff = BackoffStrategy::custom($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $backoff->generateTestSequence(5)['delay']);

        // jitter disallowed, but enabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $backoff = BackoffStrategy::custom($algorithm)->fullJitter();
        self::assertSame([1, 1, 1, 1, 1], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that the backoff handler can apply max attempts to the process.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_apply_max_attempts(): void
    {
        // check that the handler stops after 5 attempts
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5, // <<< max attempts
        );
        self::assertSame([1, 2, 3, 4], $handler->generateTestSequence(10)['delay']); // 4 delays

        // check that the handler has stopped and won't continue
        $handler->performBackoffLogic();
        self::assertNull($handler->getDelay());



        // check that the handler doesn't start when the max attempts is 0
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            0, // <<< max attempts
        );
        self::assertSame([], $handler->generateTestSequence(10)['delay']); // no delays

        // check that the handler has stopped and won't continue
        $handler->performBackoffLogic();
        self::assertNull($handler->getDelay());



        // check that the handler stops when a callback says to
        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;

        $handler = new BackoffStrategy(
            new CallbackBackoffAlgorithm($callback),
        );
        self::assertSame([1, 2, 3, 4], $handler->generateTestSequence(10)['delay']); // 4 delays

        // check that the handler has stopped and won't continue
        $handler->performBackoffLogic();
        self::assertNull($handler->getDelay());



        // check that the handler stops when the sequence ends
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]),
        );
        self::assertSame([1, 2, 3, 4, 5], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler can apply max-delay to the process.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_apply_max_delay(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            5, // <<< max-delay
        );
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler can apply unit types to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_apply_unit_types(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_SECONDS, // <<< unit type
        );

        // check the delay reported is null before starting
        self::assertNull($handler->getDelay());
        self::assertNull($handler->getDelayInSeconds());
        self::assertNull($handler->getDelayInMs());
        self::assertNull($handler->getDelayInUs());

        $delays = $handler->generateTestSequence(5);
        self::assertSame([1, 2, 3, 4, 5], $delays['delay']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInSeconds']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delayInUs']);



        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< unit type
        );

        $delays = $handler->generateTestSequence(5);
        self::assertSame([1, 2, 3, 4, 5], $delays['delay']);
        self::assertSame([0.001, 0.002, 0.003, 0.004, 0.005], $delays['delayInSeconds']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInMs']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInUs']);



        // usa a delay of 1_000_000 microseconds so that when converted
        // to milliseconds (which is cast to an int), the outcome can be checked accurately
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_000),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $handler->generateTestSequence(5);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delay']);
        self::assertSame([1, 2, 3, 4, 5], $delays['delayInSeconds']);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $delays['delayInUs']);



        // test a number which kills some rounding based mutants
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_001),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $handler->generateTestSequence(5);
        self::assertSame([1000, 2000, 3000, 4000, 5000], $delays['delayInMs']);



        // test a number which kills some rounding based mutants
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1_000_999),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        $delays = $handler->generateTestSequence(5);
        self::assertSame([1001, 2002, 3003, 4004, 5005], $delays['delayInMs']);



        // test a number which kills some rounding based mutants
        $handler = new BackoffStrategy(
            new FixedBackoffAlgorithm(1.000_000_1),
        );

        $delays = $handler->generateTestSequence(1);
        self::assertSame([1_000_000], $delays['delayInUs']);



        // test a number which kills some rounding based mutants
        $handler = new BackoffStrategy(
            new FixedBackoffAlgorithm(1.000_000_5),
        );

        $delays = $handler->generateTestSequence(1);
        self::assertSame([1_000_001], $delays['delayInUs']);
    }



    /**
     * Test that the backoff handler start with and without the first iteration.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_start_with_the_first_iteration(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            true, // <<< start with the first iteration
        );
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $handler->generateTestSequence(10)['delay']);

        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false, // <<< start without the first iteration
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler can insert an immediate first retry.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_can_insert_an_immediate_first_retry(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(5, 1),
            null,
            null,
            null,
            null,
            true,
            true, // <<< insert an immediate retry
        );
        self::assertSame([null, 0, 5, 6, 7, 8, 9, 10, 11, 12], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler disable delays.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_disable_delays(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true, // <<< enable delays
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $handler->generateTestSequence(10)['delay']);

        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $handler->generateTestSequence(10)['delay']);

        // make sure it stops when the backoff algorithm says to
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler disable retries.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_handler_can_disable_retries(): void
    {
        $handler = new BackoffStrategy(
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
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $handler->generateTestSequence(10)['delay']);

        $handler = new BackoffStrategy(
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
        self::assertSame([], $handler->generateTestSequence(10)['delay']);
    }



    /**
     * Test that the backoff handler return the current attempt number.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_current_attempt_number_can_be_retrieved(): void
    {
        // when running $backoff->step() at the end of the loop
        $backoff = BackoffStrategy::linear(1)->unitUs()->maxAttempts(5);
        $attemptNumbers = [];
        do {
            $attemptNumbers[] = $backoff->getAttemptNumber();
        } while ($backoff->step());
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);



        // when running $backoff->step() at the beginning of the loop
        $backoff = BackoffStrategy::linear(1)->unitUs()->maxAttempts(5)->runsBeforeFirstAttempt();
        $attemptNumbers = [];
        while ($backoff->step()) {
            $attemptNumbers[] = $backoff->getAttemptNumber();
        }
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);
    }



    /**
     * Test that the backoff handler return the previously calculated delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_previously_calculated_delay_can_be_retrieved(): void
    {
        // when running $backoff->step() at the end of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $backoff = BackoffStrategy::linear(1)->maxAttempts(5);
        do {
            $delays[] = $backoff->getDelay();
            $delaysSeconds[] = $backoff->getDelayInSeconds();
            $delaysMilliseconds[] = $backoff->getDelayInMs();
            $delaysMicroseconds[] = $backoff->getDelayInUs();

            // perform the logic manually to avoid the sleep
        } while ($backoff->performBackoffLogic() && !$backoff->shouldStop());

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);



        // when running $backoff->step() at the beginning of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $backoff = BackoffStrategy::linear(1)->maxAttempts(5)->runsBeforeFirstAttempt();
        // perform the logic manually to avoid the sleep
        while ($backoff->performBackoffLogic() && !$backoff->shouldStop()) {

            $delays[] = $backoff->getDelay();
            $delaysSeconds[] = $backoff->getDelayInSeconds();
            $delaysMilliseconds[] = $backoff->getDelayInMs();
            $delaysMicroseconds[] = $backoff->getDelayInUs();
        }

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);
    }





    /**
     * Check that the backoff handler's performStepLogic() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_calculate_delay_returns_true_or_false_properly(): void
    {
        $handler = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            2,
            null,
            null,
            true, // <<< start with the first iteration
        );
        self::assertTrue($handler->performBackoffLogic()); // is first attempt
        self::assertTrue($handler->performBackoffLogic()); // is second attempt
        self::assertFalse($handler->performBackoffLogic()); // too many attempts
        self::assertFalse($handler->performBackoffLogic()); // already stopped



        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
        );
        self::assertTrue($handler->performBackoffLogic());
        self::assertFalse($handler->performBackoffLogic()); // the backoff algorithm chose to stop
    }

    /**
     * Test that the backoff handler applies bounds properly to the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_bounds_are_applied_properly(): void
    {
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([-1, 0, 1]),
            null,
            null,
            0.5, // <<< max-delay
        );

        $handler->performBackoffLogic();
        self::assertSame(0, $handler->getDelay());
        $handler->performBackoffLogic();
        self::assertSame(0, $handler->getDelay());
        $handler->performBackoffLogic();
        self::assertSame(0.5, $handler->getDelay());
    }

    /**
     * Check that the backoff handler's step() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_step_returns_true_or_false_properly(): void
    {
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );
        self::assertTrue($handler->step());
        self::assertFalse($handler->step());
    }

    /**
     * Check that the backoff handler's sleep() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_sleep_returns_true_or_false_properly(): void
    {
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([0, 1]),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );
        self::assertTrue($handler->sleep()); // hasn't started yet
        $handler->performBackoffLogic();
        self::assertTrue($handler->sleep()); // first sleep is 0
        $handler->performBackoffLogic();
        self::assertTrue($handler->sleep()); // second sleep is 1
        $handler->performBackoffLogic();
        self::assertFalse($handler->sleep()); // has finished
    }

    /**
     * Check that the backoff handler's shouldStop() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_should_stop_returns_true_or_false_properly(): void
    {
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
        );
        self::assertFalse($handler->shouldStop());
        // the first attempt would happen here
        $handler->performBackoffLogic();
        // the first delay would occur here
        self::assertFalse($handler->shouldStop());
        // the second attempt would happen here
        $handler->performBackoffLogic();
        // there are no more delays to occur
        self::assertTrue($handler->shouldStop()); // has finished


        // check that it should be stopped to begin with when max attempts is 0
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
            null,
            0, // <<< max attempts
        );
        self::assertTrue($handler->shouldStop());
    }

    /**
     * Test that the backoff handler throws an exception.
     *
     * @test
     *
     * @return void
     */
    public function test_that_backoff_handler_throws_an_exception(): void
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
    public function test_the_backoff_generate_test_sequence_method(): void
    {
        // test edge-cases inside sleep()
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
        );
        $delays = $handler->generateTestSequence(10);
        self::assertSame(4, $delays['sleepCallCount']);
        self::assertSame(3, $delays['actualTimesSlept']);

        // test the run-away limit
        $handler = new BackoffStrategy(
            new SequenceBackoffAlgorithm([10]),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< ensure the time slept is big enough to detect, but not too big.
        );
        $handler->performBackoffLogic();
        $start = microtime(true);
        $handler->sleep();
        $end = microtime(true);
        self::assertGreaterThanOrEqual(0.01, $end - $start);
    }
}
