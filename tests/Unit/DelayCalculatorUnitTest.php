<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\DecorrelatedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\RandomBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\DelayCalculator;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the DelayCalculator class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class DelayCalculatorUnitTest extends PHPUnitTestCase
{
    /**
     * Test that getBaseDelay() returns the same result each time.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_get_base_delay_returns_the_same_results_each_time(): void
    {
        $delayCalculator = new DelayCalculator(
            new RandomBackoffAlgorithm(1, 2),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );

        self::assertNull($delayCalculator->getBaseDelay(0));

        $delay1a = $delayCalculator->getBaseDelay(1);
        $delay1b = $delayCalculator->getBaseDelay(1);
        $delay1c = $delayCalculator->getBaseDelay(1);
        self::assertIsFloat($delay1a);
        self::assertSame($delay1a, $delay1b);
        self::assertSame($delay1a, $delay1c);

        $delay2a = $delayCalculator->getBaseDelay(2);
        $delay2b = $delayCalculator->getBaseDelay(2);
        $delay2c = $delayCalculator->getBaseDelay(2);
        self::assertIsFloat($delay2a);
        self::assertSame($delay2a, $delay2b);
        self::assertSame($delay2a, $delay2c);
    }



    /**
     * Test that the getBaseDelay() method uses the backoff algorithm to calculate the delay.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_base_delay_uses_the_algorithm(): void
    {
        $makeNumeric = fn(int|float|null $value): int|float => is_numeric($value)
            ? $value
            : 0;

        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 1.5, 4, 8]),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(1, $delayCalculator->getBaseDelay(1));
        self::assertSame(1.5, $delayCalculator->getBaseDelay(2));
        self::assertSame(4, $delayCalculator->getBaseDelay(3));
        self::assertSame(8, $delayCalculator->getBaseDelay(4));
        self::assertNull($delayCalculator->getBaseDelay(5));



        // test that the previous delay is passed to the algorithm
        $delayCalculator = new DelayCalculator(
            new CallbackBackoffAlgorithm(
                fn(int $retryNumber, int|float|null $prevBaseDelay) => $makeNumeric($prevBaseDelay) + 2
            ),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(2, $delayCalculator->getBaseDelay(1));
        self::assertSame(4, $delayCalculator->getBaseDelay(2));
        self::assertSame(6, $delayCalculator->getBaseDelay(3));
        self::assertSame(8, $delayCalculator->getBaseDelay(4));
        self::assertSame(10, $delayCalculator->getBaseDelay(5));
    }



    /**
     * Test that the getBaseDelay() method applies bounds - i.e. between 0 and $maxDelay.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_base_delay_applies_bounds(): void
    {
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, -1.5, 4, -8]),
            new FullJitter(),
            null,
            3,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(1, $delayCalculator->getBaseDelay(1));
        self::assertSame(0, $delayCalculator->getBaseDelay(2));
        self::assertSame(3, $delayCalculator->getBaseDelay(3));
        self::assertSame(0, $delayCalculator->getBaseDelay(4));
        self::assertNull($delayCalculator->getBaseDelay(5));
    }



    /**
     * Test that the getBaseDelay() method applies $immediateFirstRetry.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_base_delay_applies_immediate_first_retry(): void
    {
        $makeNumeric = fn(int|float|null $value): int|float => is_numeric($value)
            ? $value
            : 0;

        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 1.5, 4, 8]),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            true,
            true,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(0, $delayCalculator->getBaseDelay(1));
        self::assertSame(1, $delayCalculator->getBaseDelay(2));
        self::assertSame(1.5, $delayCalculator->getBaseDelay(3));
        self::assertSame(4, $delayCalculator->getBaseDelay(4));
        self::assertSame(8, $delayCalculator->getBaseDelay(5));
        self::assertNull($delayCalculator->getBaseDelay(6));



        // test that the previous delay is passed to the algorithm when $immediateFirstRetry is true
        $delayCalculator = new DelayCalculator(
            new CallbackBackoffAlgorithm(
                fn(int $retryNumber, int|float|null $prevBaseDelay) => $makeNumeric($prevBaseDelay) + 2
            ),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            true,
            true,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(0, $delayCalculator->getBaseDelay(1));
        self::assertSame(2, $delayCalculator->getBaseDelay(2));
        self::assertSame(4, $delayCalculator->getBaseDelay(3));
        self::assertSame(6, $delayCalculator->getBaseDelay(4));
        self::assertSame(8, $delayCalculator->getBaseDelay(5));
        self::assertSame(10, $delayCalculator->getBaseDelay(6));
    }



    /**
     * Test that getBaseDelay() returns 0 when delays are disabled.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_base_when_delays_are_disabled(): void
    {
        $delayCalculator = new DelayCalculator(
            new RandomBackoffAlgorithm(1, 2),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            false,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(0, $delayCalculator->getBaseDelay(1));
        self::assertSame(0, $delayCalculator->getBaseDelay(2));
        self::assertSame(0, $delayCalculator->getBaseDelay(3));
        self::assertSame(0, $delayCalculator->getBaseDelay(4));
        self::assertSame(0, $delayCalculator->getBaseDelay(5));



        // test that the backoff algorithm can still stop the process
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 1.5, 4, 8]),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            false,
        );
        self::assertNull($delayCalculator->getBaseDelay(0));
        self::assertSame(0, $delayCalculator->getBaseDelay(1));
        self::assertSame(0, $delayCalculator->getBaseDelay(2));
        self::assertSame(0, $delayCalculator->getBaseDelay(3));
        self::assertSame(0, $delayCalculator->getBaseDelay(4));
        self::assertNull($delayCalculator->getBaseDelay(5));
    }





    /**
     * Test that getJitteredDelay() doesn't apply jitter when it's not used.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_jittered_doesnt_apply_jitter_when_its_not_used(): void
    {
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 1.5, 4, 8]),
            null, // <<< no jitter
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertNull($delayCalculator->getJitteredDelay(0));
        self::assertSame(1, $delayCalculator->getJitteredDelay(1));
        self::assertSame(1.5, $delayCalculator->getJitteredDelay(2));
        self::assertSame(4, $delayCalculator->getJitteredDelay(3));
        self::assertSame(8, $delayCalculator->getJitteredDelay(4));
        self::assertNull($delayCalculator->getJitteredDelay(5));



        // test that jitter isn't applied when the backoff algorithm doesn't allow it
        $delayCalculator = new DelayCalculator(
            new DecorrelatedBackoffAlgorithm(1),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true
        );
        self::assertNull($delayCalculator->getJitteredDelay(0));
        self::assertSame($delayCalculator->getBaseDelay(0), $delayCalculator->getJitteredDelay(0));
        self::assertSame($delayCalculator->getBaseDelay(1), $delayCalculator->getJitteredDelay(1));
        self::assertSame($delayCalculator->getBaseDelay(2), $delayCalculator->getJitteredDelay(2));
        self::assertSame($delayCalculator->getBaseDelay(3), $delayCalculator->getJitteredDelay(3));
        self::assertSame($delayCalculator->getBaseDelay(4), $delayCalculator->getJitteredDelay(4));



        // test that jitter isn't applied when the delay is 0
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 0, 2, 0]),
            new CallbackJitter(fn(int|float $delay, int $retryNumber) => $delay + 0.1),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertNull($delayCalculator->getJitteredDelay(0));
        self::assertSame(1.1, $delayCalculator->getJitteredDelay(1));
        self::assertSame(0, $delayCalculator->getJitteredDelay(2));
        self::assertSame(2.1, $delayCalculator->getJitteredDelay(3));
        self::assertSame(0, $delayCalculator->getJitteredDelay(4));
        self::assertNull($delayCalculator->getJitteredDelay(5));
    }

    /**
     * Test that getJitteredDelay() returns the same result each time.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_jittered_delay_returns_the_same_results_each_time(): void
    {
        $delayCalculator = new DelayCalculator(
            new FixedBackoffAlgorithm(1),
            new RangeJitter(0.1, 0.9),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );

        self::assertNull($delayCalculator->getJitteredDelay(0));

        $delay1a = $delayCalculator->getJitteredDelay(1);
        $delay1b = $delayCalculator->getJitteredDelay(1);
        $delay1c = $delayCalculator->getJitteredDelay(1);
        self::assertNotSame(1, $delay1a);
        self::assertIsFloat($delay1a);
        self::assertSame($delay1a, $delay1b);
        self::assertSame($delay1a, $delay1c);

        $delay2a = $delayCalculator->getJitteredDelay(2);
        $delay2b = $delayCalculator->getJitteredDelay(2);
        $delay2c = $delayCalculator->getJitteredDelay(2);
        self::assertNotSame(1, $delay2a);
        self::assertIsFloat($delay2a);
        self::assertSame($delay2a, $delay2b);
        self::assertSame($delay2a, $delay2c);
    }



    /**
     * Test that the getJitteredDelay() method applies the minimum bound i.e. must be greater than 0.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_get_jittered_delay_applies_lower_bound(): void
    {
        // test when jitter returns a value less than 0
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1]),
            new CallbackJitter(fn(int|float $delay, int $retryNumber) => -1),
            null,
            1,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertSame(0, $delayCalculator->getJitteredDelay(1));

        // test when jitter returns a value greater than 1
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1]),
            new CallbackJitter(fn(int|float $delay, int $retryNumber) => 1.001), // <<< greater than max-delay is ok
            null,
            1,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertSame(1.001, $delayCalculator->getJitteredDelay(1));
    }





    /**
     * Test that the $maxAttempts parameter is used.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_max_attempts_are_applied(): void
    {
        // when maxRetries is null
        $delayCalculator = new DelayCalculator(
            new NoopBackoffAlgorithm(),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        for ($count = 0; $count < 1000; $count++) {
            $expected = ($count === 0)
                ? null
                : 0;
            self::assertSame($expected, $delayCalculator->getBaseDelay($count));
        }

        // when maxRetries is set
        $maxRetries = mt_rand(0, 990);
        $delayCalculator = new DelayCalculator(
            new NoopBackoffAlgorithm(),
            new FullJitter(),
            $maxRetries,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        for ($count = 0; $count < 1000; $count++) {

            $expected = ($count === 0)
                ? null
                : 0;
            $expected = ($count <= $maxRetries)
                ? $expected
                : null;

            self::assertSame($expected, $delayCalculator->getBaseDelay($count));
        }
    }





    /**
     * Test that shouldStop() returns the correct result.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_should_stop_returns_the_correct_result(): void
    {
        $delayCalculator = new DelayCalculator(
            new SequenceBackoffAlgorithm([1, 1.5, 4.5, 8.2]),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );
        self::assertFalse($delayCalculator->shouldStop(0));
        self::assertFalse($delayCalculator->shouldStop(1));
        self::assertFalse($delayCalculator->shouldStop(2));
        self::assertFalse($delayCalculator->shouldStop(3));
        self::assertFalse($delayCalculator->shouldStop(4));
        self::assertTrue($delayCalculator->shouldStop(5));
        self::assertTrue($delayCalculator->shouldStop(6));
        self::assertTrue($delayCalculator->shouldStop(7));
        self::assertTrue($delayCalculator->shouldStop(8));
        self::assertTrue($delayCalculator->shouldStop(9));
    }





    /**
     * Test that the reset() method resets the instance, so it generates new numbers.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_reset_resets_so_it_generates_new_numbers(): void
    {
        $delayCalculator = new DelayCalculator(
            new RandomBackoffAlgorithm(1, 2),
            new FullJitter(),
            null,
            null,
            Settings::UNIT_SECONDS,
            false,
            true,
        );

        $delays1a = [
            $delayCalculator->getBaseDelay(0),
            $delayCalculator->getBaseDelay(1),
            $delayCalculator->getBaseDelay(2),
            $delayCalculator->getBaseDelay(3),
            $delayCalculator->getBaseDelay(4),
        ];
        $jittered1a = [
            $delayCalculator->getJitteredDelay(0),
            $delayCalculator->getJitteredDelay(1),
            $delayCalculator->getJitteredDelay(2),
            $delayCalculator->getJitteredDelay(3),
            $delayCalculator->getJitteredDelay(4),
        ];

        $delays1b = [
            $delayCalculator->getBaseDelay(0),
            $delayCalculator->getBaseDelay(1),
            $delayCalculator->getBaseDelay(2),
            $delayCalculator->getBaseDelay(3),
            $delayCalculator->getBaseDelay(4),
        ];
        $jittered1b = [
            $delayCalculator->getJitteredDelay(0),
            $delayCalculator->getJitteredDelay(1),
            $delayCalculator->getJitteredDelay(2),
            $delayCalculator->getJitteredDelay(3),
            $delayCalculator->getJitteredDelay(4),
        ];

        // test ->reset() chaining
        self::assertSame($delayCalculator, $delayCalculator->reset());

        $delays2a = [
            $delayCalculator->getBaseDelay(0),
            $delayCalculator->getBaseDelay(1),
            $delayCalculator->getBaseDelay(2),
            $delayCalculator->getBaseDelay(3),
            $delayCalculator->getBaseDelay(4),
        ];
        $jittered2a = [
            $delayCalculator->getJitteredDelay(0),
            $delayCalculator->getJitteredDelay(1),
            $delayCalculator->getJitteredDelay(2),
            $delayCalculator->getJitteredDelay(3),
            $delayCalculator->getJitteredDelay(4),
        ];

        self::assertSame($delays1a, $delays1b);
        self::assertSame($jittered1a, $jittered1b);

        self::assertNotSame($delays1a, $delays2a);
        self::assertNotSame($jittered1a, $jittered2a);
    }





    /**
     * Test that the DelayCalculator throws an exception when an invalid unit type is passed.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public function test_that_delay_calculator_throws_an_exception_due_to_invalid_unit_type(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new DelayCalculator(
            new SequenceBackoffAlgorithm([1]),
            new FullJitter(),
            null,
            null,
            'invalid', // <<< invalid unit type
            false,
            true,
        );
    }
}
