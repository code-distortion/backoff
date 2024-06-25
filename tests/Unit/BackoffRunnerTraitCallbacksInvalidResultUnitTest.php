<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\InvokableClass;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * Test the BackoffRunnerTrait - test "invalid value" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksInvalidResultUnitTest extends PHPUnitTestCase
{
    /**
     * Test which values are passed to invalid-result callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_invalid_value_callbacks_are_called(): void
    {
        $maxAttempts = 2;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();
        $createCallback = fn(&$count) => function () use (&$count) {
            $count++;
        };



        $count1 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);

        $count1 = $count2 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);


        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);


        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1))
            ->invalidResultCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);
        self::assertSame($maxAttempts, $count4);


        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1))
            ->invalidResultCallback($createCallback($count2))
            ->invalidResultCallback($createCallback($count3))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);


        // a callable array
        $invokableClass = new InvokableClass();
        $callable = [$invokableClass, '__invoke'];

        $count1 = $count2 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($callable)
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $invokableClass->getCount());


        // triggered via ->retryUntil()
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->retryUntil(11)
            ->invalidResultCallback($createCallback($count1))
            ->invalidResultCallback($createCallback($count2))
            ->invalidResultCallback($createCallback($count3))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);
    }

    /**
     * Test that $willRetry that's passed to invalid-result callbacks is passed correctly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_will_retry_is_passed_to_invalid_value_callbacks_correctly(): void
    {
        $maxAttempts = mt_rand(0, 10);
        $newNoopBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();
        $newSequenceBackoff = fn() => Backoff::sequenceUs([1, 2, 3])->retryExceptions();

        $createCallback = fn(&$count, $expectedWillRetry, $actualMaxAttempts)
            => function (
                mixed $result,
                AttemptLog $log,
                bool $willRetry
            ) use (
                &$count,
                $expectedWillRetry,
                $actualMaxAttempts,
            ) {
                // use $expectedWillRetry, until the last attempt
                $count++;
                $expectedWillRetry = ($count < $actualMaxAttempts)
                    ? $expectedWillRetry
                    : false;

                self::assertSame($expectedWillRetry, $willRetry); // <<<
            };

        // noop - WILL retry because of an invalid return value
        $count1 = 0;
        $newNoopBackoff()
            ->retryWhen(false)
            ->invalidResultCallback($createCallback($count1, true, $maxAttempts))
            ->attempt(fn() => false, null);

        // sequence (which has 3 retries) - WILL retry because of an invalid return value
        $count1 = 0;
        $newSequenceBackoff()
            ->retryWhen(false)
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->invalidResultCallback($createCallback($count1, true, 4))
            ->attempt(fn() => false, null);
    }

    /**
     * Test that exceptions thrown by callbacks passed to ->invalidResultCallback(), are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_invalid_value_callback_exceptions_are_thrown(): void
    {
        $exception = new Exception();
        $count = 0;
        $callback = function (mixed $result, AttemptLog $log) use (&$count, $exception): bool {
            $count++;
            throw $exception;
        };

        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(2)
                ->retryWhen(10)
                ->invalidResultCallback($callback)
                ->attempt(fn() => 10, null);
        } catch (Throwable $e) {
        }

        self::assertSame(1, $count);
        self::assertSame($exception, $e);
    }
}
