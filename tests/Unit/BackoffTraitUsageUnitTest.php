<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Traits\BackoffStrategyDecoratorTrait;
use CodeDistortion\Backoff\Traits\BackoffRunnerTrait;
use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test that the backoff classes use the correct traits and implement the correct interfaces.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffTraitUsageUnitTest extends PHPUnitTestCase
{
    /**
     * Check to make sure the backoff classes use the correct traits.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_classes_use_the_correct_traits(): void
    {
        $traits = class_uses(Backoff::class);
        self::assertArrayHasKey(BackoffStrategyDecoratorTrait::class, $traits);
        self::assertArrayHasKey(BackoffRunnerTrait::class, $traits);
        self::assertArrayHasKey(BackoffStrategyTrait::class, $traits);
    }
}
