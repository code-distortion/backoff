<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\FixedBackoffWithNoJitterAlgorithm;
use CodeDistortion\Backoff\Tests\Unit\Support\TestSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the BackoffStrategyTrait - test the internals. i.e. test things inside that happen intrinsically, but aren't
 * necessarily obvious from the public methods being called.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyTraitInternalsUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the initial stopped state is resolved properly, which shows whether it's allowed to start, or if it's
     * already "stopped".
     *
     * @test
     * @dataProvider initialStoppedStateDataProvider
     *
     * @param integer|null $maxAttempts     The max attempts to test with.
     * @param boolean      $expectedStopped The expected stopped state.
     * @return void
     */
    #[Test]
    #[DataProvider('initialStoppedStateDataProvider')]
    public static function test_initial_stopped_state(?int $maxAttempts, bool $expectedStopped): void
    {
        $backoff = new BackoffStrategy(
            new FixedBackoffAlgorithm(1),
            null,
            $maxAttempts,
        );
        self::assertSame($expectedStopped, $backoff->hasStopped());
        self::assertSame(!$expectedStopped, $backoff->canContinue());
    }

    /**
     * DataProvider for test_initial_stopped_state.
     *
     * @return array<string,array<string,integer|boolean|null>>
     */
    public static function initialStoppedStateDataProvider(): array
    {
        return [
            'no max attempts' => [
                'maxAttempts' => null,
                'expectedStopped' => false,
            ],
            'negative max attempts' => [
                'maxAttempts' => -1,
                'expectedStopped' => true,
            ],
            'zero max attempts' => [
                'maxAttempts' => 0,
                'expectedStopped' => true,
            ],
            'positive max attempts' => [
                'maxAttempts' => 1,
                'expectedStopped' => false,
            ],
        ];
    }



    /**
     * Test that the backoff strategy stops when the backoff algorithm says to.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_stops_when_the_backoff_algorithm_says_to(): void
    {
        // callback algorithm
        $callback = fn($retryNumber) => $retryNumber <= 3
            ? $retryNumber
            : null; // stop after 3 delays

        $algorithm = new CallbackBackoffAlgorithm($callback);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 2, 3], $strategy->generateTestSequence(10)->getDelays()); // 3 retry delays

        // check that the strategy has stopped now and won't continue
        self::assertNull($strategy->getDelay());



        // check that the strategy stops when the sequence ends
        $algorithm = new SequenceBackoffAlgorithm([1, 2, 3, 4, 5]);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 2, 3, 4, 5], $strategy->generateTestSequence(10)->getDelays()); // 5 retry delays

        // check that the strategy has stopped now and won't continue
        self::assertNull($strategy->getDelay());
    }



    /**
     * Test that the backoff strategy reports delays as null before starting and after stopping.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_delays_are_null_before_starting_and_after_stopping(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
        );

        // check that the delay reported is null before starting
        self::assertNull($strategy->getDelay());
        self::assertNull($strategy->getDelayInSeconds());
        self::assertNull($strategy->getDelayInMs());
        self::assertNull($strategy->getDelayInUs());

        $strategy->generateTestSequence(2);

        // check that they do change from null
        self::assertNotNull($strategy->getDelay());
        self::assertNotNull($strategy->getDelayInSeconds());
        self::assertNotNull($strategy->getDelayInMs());
        self::assertNotNull($strategy->getDelayInUs());

        $strategy->generateTestSequence(2);

        // check that the delay reported is null after stopping
        self::assertNull($strategy->getDelay());
        self::assertNull($strategy->getDelayInSeconds());
        self::assertNull($strategy->getDelayInMs());
        self::assertNull($strategy->getDelayInUs());
    }



    /**
     * Test that the base_delay is passed through to the jitter.
     *
     * This is done inside the DelayCalculator now, however it's good to know that the delay is being passed through,
     * regardless of the internals.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_passes_the_base_delay_to_jitter()
    {
        $callback = fn($delay, $retryNumber) => $delay;

        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([5, 2, 4, 6, 3, 1]),
            new CallbackJitter($callback),
        );

        self::assertSame([5, 2, 4, 6, 3, 1], $strategy->generateTestSequence(20)->getDelays());
    }

    /**
     * Test that the correct retry number is passed to the jitter, when applying jitter to the delay.
     *
     * This is done inside the DelayCalculator now, but it gets the retry_count from the caller. It's also good to know
     * that the delay is being passed through, regardless of the internals.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_passes_the_retry_number_to_jitter()
    {
        $callback = fn($delay, $retryNumber) => $retryNumber;

        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm(1),
            new CallbackJitter($callback),
            10,
        );

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $strategy->generateTestSequence(20)->getDelays());
    }

    /**
     * Test that backoff strategies can dictate when jitter is allowed to be applied, and that the backoff strategy
     * respects this.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_only_applies_jitter_when_allowed_and_desired(): void
    {
        // jitter allowed, but disabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)->getDelays());

        // jitter allowed, and enabled
        $algorithm = new FixedBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());
        self::assertNotSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)->getDelays());

        // jitter disallowed, and disabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)->getDelays());

        // jitter disallowed, but enabled
        $algorithm = new FixedBackoffWithNoJitterAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());
        self::assertSame([1, 1, 1, 1, 1], $strategy->generateTestSequence(5)->getDelays());
    }



    /**
     * Test that the backoff strategy applies bounds to the delays - i.e. between 0 and max-delay.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_applies_bounds(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([-1, 0, 1]),
            null,
            null,
            0.5, // <<< max-delay
        );

        $strategy->step(false);
        self::assertSame(0, $strategy->getDelay());
        $strategy->step(false);
        self::assertSame(0, $strategy->getDelay());
        $strategy->step(false);
        self::assertSame(0.5, $strategy->getDelay());
    }



    /**
     * Test that backoff strategy can generate integer delays, and with decimal places.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_generates_integer_and_float_delays(): void
    {
        $unit = Settings::UNIT_SECONDS;

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            $unit, // <<< unit type
        );
        $generatedDelays = $strategy->generateTestSequence(5);
        self::assertSame([1, 2, 3, 4, 5], $generatedDelays->getDelays());
        self::assertSame([1, 2, 3, 4, 5], $generatedDelays->getDelaysInSeconds());
        self::assertSame([1000, 2000, 3000, 4000, 5000], $generatedDelays->getDelaysInMs());
        self::assertSame([1_000_000, 2_000_000, 3_000_000, 4_000_000, 5_000_000], $generatedDelays->getDelaysInUs());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1.5),
            null,
            null,
            null,
            $unit, // <<< unit type
        );
        $generatedDelays = $strategy->generateTestSequence(5);
        self::assertSame([1.5, 3.0, 4.5, 6.0, 7.5], $generatedDelays->getDelays());
        self::assertSame([1.5, 3.0, 4.5, 6.0, 7.5], $generatedDelays->getDelaysInSeconds());
        self::assertSame([1500.0, 3000.0, 4500.0, 6000.0, 7500.0], $generatedDelays->getDelaysInMs());
        self::assertSame(
            [1_500_000.0, 3_000_000.0, 4_500_000.0, 6_000_000.0, 7_500_000.0],
            $generatedDelays->getDelaysInUs(),
        );
    }



    /**
     * Test that the backoff strategy returns the correct currentAttemptAsNumber().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_current_attempt_as_number_returns_correctly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([]),
            null,
            null,
            null,
            Settings::UNIT_SECONDS,
            true
        );

        self::assertNull($strategy->currentAttemptNumber());
        self::assertSame(0, TestSupport::callPrivateMethod($strategy, 'currentAttemptAsNumber'));
    }

    /**
     * Test that the backoff strategy returns the correct currentRetryAsNumber().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_current_retry_as_number_returns_correctly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([]),
            null,
            null,
            null,
            Settings::UNIT_SECONDS,
            true
        );

        self::assertNull(TestSupport::callPrivateMethod($strategy, 'currentRetryNumber'));
        self::assertSame(0, TestSupport::callPrivateMethod($strategy, 'currentRetryAsNumber'));
    }

    /**
     * Test that the backoff strategy returns the correct getDelayAsNumber().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_delay_as_number_returns_correctly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([]),
            null,
            null,
            null,
            Settings::UNIT_SECONDS,
            true
        );

        self::assertNull($strategy->getDelay());
        self::assertSame(0, TestSupport::callPrivateMethod($strategy, 'getDelayAsNumber'));
    }

    /**
     * Test that the backoff strategy sets the started flag correctly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_started_is_triggered(): void
    {
        // each of these methods should trigger the "started" flag
        $methods = [
            'step' => [false],
            'sleep' => [],
            'startOfAttempt' => [],
            'isLastAttempt' => [],
            'getDelay' => [],
            'simulate' => [5, 10],
            'simulateInSeconds' => [5, 10],
            'simulateInMs' => [5, 10],
            'simulateInUs' => [5, 10],
        ];

        $algorithm = new NoopBackoffAlgorithm();

        foreach ($methods as $method => $args) {
            $strategy = new BackoffStrategy($algorithm);
            self::assertFalse(TestSupport::getPrivateProperty($strategy, 'started'));
            call_user_func_array([$strategy, $method], $args);
            self::assertTrue(TestSupport::getPrivateProperty($strategy, 'started'));
        }

        // check that the sleep method triggers the "started" flag
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            0, // make sure sleep quits early, after calling ->starting()
        );
        TestSupport::setPrivateProperty($strategy, 'attemptLogs', ['test']);
        self::assertFalse(TestSupport::getPrivateProperty($strategy, 'started'));
        $strategy->sleep();
        self::assertSame([], $strategy->logs());
        self::assertNotSame(['test'], $strategy->logs());
    }



    /**
     * Test that the sleep() method uses the time recorded earlier by the step() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_sleep_uses_the_time_recorded_by_the_step_method()
    {
        $delayMs = TestSupport::isWindowsOrMac()
            ? 1000
            : 100;
        $halfDelayMs = $delayMs / 2;

        // within a margin of +/- 20% should be plenty
        // note: this timing varies greatly on Windows and somewhat on Mac
        $margin = TestSupport::isWindowsOrMac()
            ? 1.75
            : 1.2;

        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm($delayMs),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS,
            false,
        );

        // no time should be slept until the next step
        self::checkDurationInMs($strategy, 'sleep', [], 0, 10);

        // the full sleep should be slept
        $strategy->step(false);
        self::checkDurationInMs($strategy, 'sleep', [], $delayMs / $margin, $delayMs * $margin);

        // sleep for HALF of the duration outside, before calling sleep()
        // the remaining half should be slept by the sleep() method
        $strategy->step(false);
        usleep($halfDelayMs * 1000);
        self::checkDurationInMs($strategy, 'sleep', [], $halfDelayMs / $margin, $halfDelayMs * $margin);

        // sleep for the FULL duration outside, before calling sleep()
        // no time should be slept by the sleep() method
        $strategy->step(false);
        usleep($delayMs * 1000);
        self::checkDurationInMs($strategy, 'sleep', [], 0, $delayMs / 10);
    }



    /**
     * Check that a method takes a certain amount of time to run.
     *
     * @param object  $instance     The instance to call the method on.
     * @param string  $method       The method to call.
     * @param mixed[] $args         The arguments to pass to the method.
     * @param float   $lowerBoundMs The lower bound of the time the method should take to run.
     * @param float   $upperBoundMs The upper bound of the time the method should take to run.
     * @return void
     */
    private static function checkDurationInMs(
        object $instance,
        string $method,
        array $args,
        float $lowerBoundMs,
        float $upperBoundMs,
    ): void {

        $start = microtime(true);
        $callable = [$instance, $method];
        if (is_callable($callable)) {
            call_user_func_array($callable, $args);
        }
        $end = microtime(true);
        $diffInMs = ($end - $start) * 1000;

        self::assertGreaterThan($lowerBoundMs, $diffInMs);
        self::assertLessThan($upperBoundMs, $diffInMs);
    }
}
