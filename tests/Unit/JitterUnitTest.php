<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\EqualJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\TestSupport;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the Jitter classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class JitterUnitTest extends PHPUnitTestCase
{
    /**
     * Test the RangeJitter class.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_range_jitter(): void
    {
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $jitter = new RangeJitter($min, $max);

        for ($count = 0; $count < 100; $count++) {
            $delay = $jitter->apply(1, 99);
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }
    }

    /**
     * Test the CallbackJitter class.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_callback_jitter(): void
    {
        // test that delay is passed
        $callback = fn(int|float $delay, int $retryNumber) => $delay + 1;
        $jitter = new CallbackJitter($callback);

        self::assertEquals(2, $jitter->apply(1, 10));
        self::assertEquals(3, $jitter->apply(2, 11));
        self::assertEquals(4, $jitter->apply(3, 12));

        // test that retry-number is passed
        $callback = fn(int|float $delay, int $retryNumber) => $retryNumber + 1;
        $jitter = new CallbackJitter($callback);
        self::assertEquals(11, $jitter->apply(1, 10));
        self::assertEquals(12, $jitter->apply(2, 11));
        self::assertEquals(13, $jitter->apply(3, 12));

        // int return
        $callback = fn(int|float $delay, int $retryNumber) => $delay + 1;
        $jitter = new CallbackJitter($callback);
        self::assertEquals(2, $jitter->apply(1, 10));
        self::assertEquals(3, $jitter->apply(2, 11));
        self::assertEquals(4, $jitter->apply(3, 12));

        // float return
        $callback = fn(int|float $delay, int $retryNumber) => $delay + 0.1;
        $jitter = new CallbackJitter($callback);
        self::assertEquals(1.1, $jitter->apply(1.0, 10));
        self::assertEquals(2.1, $jitter->apply(2.0, 11));
        self::assertEquals(3.1, $jitter->apply(3.0, 12));

        // return something other than int or float
        $callback = fn(int|float $delay, int $retryNumber) => 'a string';
        $jitter = new CallbackJitter($callback);
        self::assertEquals(1, $jitter->apply(1.0, 10));
        self::assertEquals(1, $jitter->apply(2.0, 11));
        self::assertEquals(1, $jitter->apply(3.0, 12));
    }



    /**
     * Check the range of responses the different jitter classes generate.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_range_of_responses_the_different_jitter_classes_generate(): void
    {
        // lower bound is 0
        self::checkJitterResponses(new RangeJitter(-1, -1), 1, 0, 0);
        self::checkJitterResponses(new RangeJitter(-1, 0), 1, 0, 0);
        self::checkJitterResponses(new RangeJitter(0, 0), 1, 0, 0);
        self::checkJitterResponses(new RangeJitter(-1, 1), 1, 0, 1);

        // ranges above 0
        self::checkJitterResponses(new RangeJitter(0, 0.25), 1, 0, 0.25);
        self::checkJitterResponses(new RangeJitter(0.25, 0.5), 1, 0.25, 0.5);
        self::checkJitterResponses(new RangeJitter(0.5, 0.75), 1, 0.5, 0.75);
        self::checkJitterResponses(new RangeJitter(0.75, 1), 1, 0.75, 1);
        self::checkJitterResponses(new RangeJitter(0, 1), 1, 0, 1);
        self::checkJitterResponses(new RangeJitter(0.75, 1.25), 1, 0.75, 1.25);
        self::checkJitterResponses(new RangeJitter(1, 2), 1, 1, 2);

        // float delay
        self::checkJitterResponses(new RangeJitter(1, 2), 0.5, 0.5, 1);

        // larger delay
        self::checkJitterResponses(new RangeJitter(1, 2), 100, 100, 200);

        // full jitter
        self::checkJitterResponses(new FullJitter(), 1, 0, 1);
        self::checkJitterResponses(new FullJitter(), 100, 0, 100);

        // equal jitter
        self::checkJitterResponses(new EqualJitter(), 1, 0.5, 1);
        self::checkJitterResponses(new EqualJitter(), 100, 50, 100);
    }

    /**
     * Consult a jitter instance many times to become more sure the responses are within the expected range.
     *
     * @param JitterInterface $jitter      The jitter instance to test.
     * @param integer|float   $delay       The delay to apply the jitter to.
     * @param integer|float   $expectedMin The minimum value that should be found.
     * @param integer|float   $expectedMax The maximum value that should be found.
     * @return void
     */
    private static function checkJitterResponses(
        JitterInterface $jitter,
        int|float $delay,
        int|float $expectedMin,
        int|float $expectedMax
    ): void {

        for ($count = 0; $count < 100; $count++) {
            $jitteredDelay = $jitter->apply($delay, 99);
            self::assertGreaterThanOrEqual($expectedMin, $jitteredDelay);
            self::assertLessThanOrEqual($expectedMax, $jitteredDelay);
        }
    }



    /**
     * Test that RangeJitter throws an exception when max is less than min.
     *
     * @test
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    #[Test]
    public function test_that_range_jitter_throws_exception_when_max_is_less_than_min(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new RangeJitter(1, 0);
    }

    /**
     * Test that RangeJitter throws an exception when max is less than min.
     *
     * @test
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    #[Test]
    public function test_that_range_jitter_throws_exception_when_max_is_less_than_min2(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new RangeJitter(-1, -2);
    }

    /**
     * Test that RangeJitter applies the lower bound of 0 to the min and max values.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_range_jitter_applies_lower_bound_properly(): void
    {
        $jitter = new RangeJitter(-3, -2);
        $min = TestSupport::getPrivateProperty($jitter, 'min');
        $max = TestSupport::getPrivateProperty($jitter, 'max');
        self::assertSame(0, $min);
        self::assertSame(0, $max);
    }

    /**
     * Test that BaseJitter returns zero when min and max are invalid.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_base_jitter_returns_0_when_min_and_max_are_invalid(): void
    {
        $jitter = new RangeJitter(0, 1);
        TestSupport::setPrivateProperty($jitter, 'min', 2); // an invalid value where $min > $max

        self::assertSame(0, $jitter->apply(1, 1));
    }
}
