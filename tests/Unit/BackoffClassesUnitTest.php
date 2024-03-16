<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Traits\BackoffDecoratorTrait;
use CodeDistortion\Backoff\Traits\BackoffRunnerTrait;
use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * Test that the backoff classes use the correct traits and implement the correct interfaces.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffClassesUnitTest extends PHPUnitTestCase
{
    /**
     * Check to make sure the backoff classes use the correct traits.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_classes_use_the_correct_traits(): void
    {
        $traits = class_uses(Backoff::class);
        self::assertArrayHasKey(BackoffDecoratorTrait::class, $traits);
        self::assertArrayHasKey(BackoffRunnerTrait::class, $traits);
        self::assertArrayHasKey(BackoffStrategyTrait::class, $traits);
    }

//    /**
//     * Check to make sure the backoff classes implement the correct interfaces.
//     *
//     * @test
//     *
//     * @return void
//     */
//    public static function test_that_classes_implement_the_correct_interfaces(): void
//    {
//        $backoff = Backoff::noop();
//        self::assertInstanceOf(SomeInterface::class, $backoff);
//        // or ?
//        self::assertInstanceOf(SomeInterface::class, Backoff::class);
//    }
}
