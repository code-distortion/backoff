<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Exceptions\BackoffException;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the BackoffException classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class ExceptionsUnitTest extends PHPUnitTestCase
{
    /**
     * Test these exceptions exist, and the helper methods for creating them exist.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_exceptions(): void
    {
        // BackoffInitialisationException
        $min = mt_rand(0, 100000);
        $max = mt_rand(0, 100000);
        $exception = BackoffInitialisationException::randMinIsGreaterThanMax($min, $max);
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffInitialisationException::class, $exception);
        self::assertSame(
            "A min value ($min) was given that is greater than the max value ($max)",
            $exception->getMessage(),
        );

        $unitType = 'invalid-unit-type';
        $exception = BackoffInitialisationException::invalidUnitType($unitType);
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffInitialisationException::class, $exception);
        self::assertSame("Invalid unit type \"$unitType\" was given", $exception->getMessage());



        // BackoffRuntimeException
        $exception = BackoffRuntimeException::customBackoffCallbackGaveInvalidReturnValue();
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffRuntimeException::class, $exception);
        self::assertSame(
            'The CallbackBackoffAlgorithm callback gave an invalid return value',
            $exception->getMessage(),
        );

        $method = 'invalid-method';
        $exception = BackoffRuntimeException::attemptToChangeAfterStart($method);
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffRuntimeException::class, $exception);
        self::assertSame(
            "Backoff strategies cannot be reconfigured after starting - attempted to call \"$method\"",
            $exception->getMessage(),
        );

        $exception = BackoffRuntimeException::startOfAttemptNotAllowed();
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffRuntimeException::class, $exception);
        self::assertSame(
            'Method ->startOfAttempt() cannot be called after the Backoff has stopped',
            $exception->getMessage(),
        );

        $exception = BackoffRuntimeException::attemptLogHasNotStarted();
        self::assertInstanceOf(BackoffException::class, $exception);
        self::assertInstanceOf(BackoffRuntimeException::class, $exception);
        self::assertSame(
            'Method ->endOfAttempt() was called without ->startOfAttempt() being called first',
            $exception->getMessage(),
        );
    }
}
