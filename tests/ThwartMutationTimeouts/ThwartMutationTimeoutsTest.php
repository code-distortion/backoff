<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\ThwartMutationTimeouts;

use CodeDistortion\Backoff\Algorithms\NoBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\DelayCalculator;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\TestSupport;
use PHPUnit\Framework\Attributes\Test;

/**
 * Perform tests designed to thwart timeouts during mutation testing.
 *
 * Some mutants cause the code to run slowly, but haven't caused an infinite loop. Killing these mutants quickly means
 * we'll get the correct answer quickly before the timeout occurs.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ThwartMutationTimeoutsTest extends PHPUnitTestCase
{
    /**
     * Test to make sure the DelayCalculator doesn't mutate the min bound from 0 to 1.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_delay_calculator_applies_min_bound_properly(): void
    {
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([0.1]),
            new CallbackJitter(fn() => 0.1),
            null,
            null,
            Settings::UNIT_MICROSECONDS,
            false,
            true,
        );
        self::assertSame(0.1, $delayCalculator->getBaseDelay(1));
        self::assertSame(0.1, $delayCalculator->getJitteredDelay(1));
    }



    /**
     * Test that the NoopBackoffAlgorithm returns 0.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_noop_backoff_algorithm_returns_0()
    {
        $algorithm = new NoopBackoffAlgorithm();
        self::assertSame(0, $algorithm->calculateBaseDelay(1, 0));
    }



    /**
     * Test that the NoneBackoffAlgorithm returns null .
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_none_backoff_algorithm_returns_null()
    {
        $algorithm = new NoBackoffAlgorithm();
        self::assertNull($algorithm->calculateBaseDelay(1, 0));
    }



    /**
     * Test to make sure the BackoffStrategy picks the unit type correctly, and doesn't use 'seconds' every time.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_backoff_strategy_unit_type_is_picked_up(): void
    {
        $strategy = new BackoffStrategy(
            new NoopBackoffAlgorithm(),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< unit type
        );

        self::assertSame(Settings::UNIT_MICROSECONDS, $strategy->getUnitType());
    }



    /**
     * Test that the BackoffStrategy's current retry number is correctly determined.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_backoff_strategy_current_retry_number_is_correctly_determined(): void
    {
        $backoff = new BackoffStrategy(
            new NoopBackoffAlgorithm(),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS,
            true,
        );

        self::assertSame(null, TestSupport::callPrivateMethod($backoff, 'currentRetryNumber'));
        self::assertSame(0, TestSupport::callPrivateMethod($backoff, 'currentRetryAsNumber'));
        $backoff->step(false);
        self::assertSame(0, TestSupport::callPrivateMethod($backoff, 'currentRetryNumber'));
        self::assertSame(0, TestSupport::callPrivateMethod($backoff, 'currentRetryAsNumber'));
        $backoff->step(false);
        self::assertSame(1, TestSupport::callPrivateMethod($backoff, 'currentRetryNumber'));
        self::assertSame(1, TestSupport::callPrivateMethod($backoff, 'currentRetryAsNumber'));
    }



    /**
     * Test to make sure the Support::randFloat() method doesn't multiply when it should divide.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_rand_float_divides(): void
    {
        // test to make sure that this line:
        //   return $randInt / $multiplier;
        // isn't mutated to:
        //   return $randInt * $multiplier;
        self::assertSame(1.0, Support::randFloat(1, 1));
    }
}
