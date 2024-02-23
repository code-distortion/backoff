<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\EqualJitter;
use CodeDistortion\Backoff\Support\JitterInterface;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;

/**
 * Test the Jitter classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class JitterUnitTest extends PHPUnitTestCase
{
    /**
     * Test the CallbackJitter class.
     *
     * @test
     *
     * @return void
     */
    public static function test_range_jitter(): void
    {
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $jitter = new RangeJitter($min, $max);

        for ($count = 0; $count < 100; $count++) {
            $delay = $jitter->apply(1);
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
    public static function test_callback_jitter(): void
    {
        // int return
        $callback = fn($delay) => $delay + 1;
        $jitter = new CallbackJitter($callback);
        self::assertEquals(2, $jitter->apply(1));
        self::assertEquals(3, $jitter->apply(2));
        self::assertEquals(4, $jitter->apply(3));

        // float return
        $callback = fn($delay) => $delay + 0.1;
        $jitter = new CallbackJitter($callback);
        self::assertEquals(1.1, $jitter->apply(1));
        self::assertEquals(2.1, $jitter->apply(2));
        self::assertEquals(3.1, $jitter->apply(3));
    }

    /**
     * Test the range of responses that jitter returns.
     *
     * @test
     *
     * @return void
     */
    public static function test_jitter_ranges(): void
    {
        self::testJitterQuadrants(new RangeJitter(-1, -1), 0, 0);
        self::testJitterQuadrants(new RangeJitter(-1, 0), 0, 0);
        self::testJitterQuadrants(new RangeJitter(0, 0), 0, 0);
        self::testJitterQuadrants(new RangeJitter(0, 0.25), 0, 1);
        self::testJitterQuadrants(new RangeJitter(0.25, 0.5), 1, 2);
        self::testJitterQuadrants(new RangeJitter(0.5, 0.75), 2, 3);
        self::testJitterQuadrants(new RangeJitter(0.75, 1), 3, 4);
        self::testJitterQuadrants(new RangeJitter(0, 1), 0, 4);
        self::testJitterQuadrants(new RangeJitter(0.75, 1.25), 3, 5);
        self::testJitterQuadrants(new RangeJitter(1, 2), 4, 8);

        self::testJitterQuadrants(new FullJitter(), 0, 4);

        self::testJitterQuadrants(new EqualJitter(), 2, 4);
    }

    /**
     * Check which quadrants the jitter falls into.
     *
     * This queries the jitter instance many times, and logs the quartiles (compared to the input) the results fall
     * into.
     *
     * @param JitterInterface $jitter      The jitter instance to test.
     * @param integer         $expectedMin The minimum value that should be found.
     * @param integer         $expectedMax The maximum value that should be found.
     * @return void
     */
    private static function testJitterQuadrants(JitterInterface $jitter, int $expectedMin, int $expectedMax): void
    {
        for ($count = 0; $count < 100; $count++) {
            $delay = $jitter->apply(4);
            self::assertGreaterThanOrEqual($expectedMin, $delay);
            self::assertLessThanOrEqual($expectedMax, $delay);
        }
    }

    /**
     * Test that Jitter throws exceptions when needed.
     *
     * @test
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    public function test_that_custom_jitter_throws_exceptions(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new RangeJitter(1, 0);
    }

    /**
     * Test that Jitter throws exceptions when needed.
     *
     * @test
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    public function test_that_custom_jitter_throws_exceptions2(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new RangeJitter(-1, -2);
    }
}
