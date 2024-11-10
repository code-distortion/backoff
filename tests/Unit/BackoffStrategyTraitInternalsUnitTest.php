<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\FixedBackoffWithNoJitterAlgorithm;
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
}
