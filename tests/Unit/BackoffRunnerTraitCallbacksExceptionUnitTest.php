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
 * Test the BackoffRunnerTrait - test "exception" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksExceptionUnitTest extends PHPUnitTestCase
{
    /**
     * Test that different combinations of exceptionCallbacks are called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_exception_callback_is_called(): void
    {
        $maxAttempts = 5;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();

        $exception = new Exception();
        $createCallback = fn(&$count)
            => function (Throwable $e, AttemptLog $log, bool $willRetry) use (&$count) {
                $count++;
            };



        $count1 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $count1);

        $count1 = $count2 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);


        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);


        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->exceptionCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);
        self::assertSame($maxAttempts, $count4);


        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->exceptionCallback($createCallback($count2))
            ->exceptionCallback($createCallback($count3))
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);


        // a callable array
        $invokableClass = new InvokableClass();
        $callable = [$invokableClass, '__invoke'];

        $count1 = $count2 = 0;
        $newBackoff()
            ->exceptionCallback($callable)
            ->attempt(fn() => throw $exception, null);
        self::assertSame($maxAttempts, $invokableClass->getCount());
    }

    /**
     * Test which exception is passed to exception callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_which_exception_is_passed_to_exception_callbacks(): void
    {
        $newBackoff = fn() => Backoff::noop()->maxAttempts(2)->retryExceptions();

        $exception = new Exception();
        $createCallback = fn(&$count)
            => function (Throwable $e, AttemptLog $log, bool $willRetry) use (&$count, $exception) {
                $count++;
                self::assertSame($exception, $e); // <<<
            };

        $count = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count))
            ->attempt(fn() => throw $exception, null);
        self::assertGreaterThan(0, $count); // just confirm that it happened
    }

    /**
     * Test that $willRetry that's passed to exception callbacks is passed correctly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_will_retry_is_passed_to_exception_callbacks_correctly(): void
    {
        $maxAttempts = mt_rand(0, 10);
        $newNoopBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();
        $newSequenceBackoff = fn() => Backoff::sequenceUs([1, 2, 3])->retryExceptions();

        $exception = new Exception();
        $createCallback = fn(&$count, $expectedWillRetry, $actualMaxAttempts)
            => function (
                Throwable $e,
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

        // noop - WILL retry exceptions
        $count1 = 0;
        $newNoopBackoff()
            ->exceptionCallback($createCallback($count1, true, $maxAttempts))
            ->attempt(fn() => throw $exception, null);

        // noop - will NOT retry exceptions
        $count1 = 0;
        $newNoopBackoff()
            ->dontRetryExceptions()
            ->exceptionCallback($createCallback($count1, false, 1))
            ->attempt(fn() => throw $exception, null);

        // sequence (which has 3 retries) - WILL retry exceptions
        $count1 = 0;
        $newSequenceBackoff()
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->exceptionCallback($createCallback($count1, true, 4))
            ->attempt(fn() => throw $exception, null);

        // sequence (which has 3 retries) - will NOT retry exceptions
        $count1 = 0;
        $newSequenceBackoff()
            ->dontRetryExceptions()
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->exceptionCallback($createCallback($count1, false, 1))
            ->attempt(fn() => throw $exception, null);
    }

    /**
     * Test that when an exception callback itself throws an exception, that this exception is rethrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_exception_callback_exceptions_are_rethrown(): void
    {
        $count = 0;
        $randInt = mt_rand();
        $callback = function () use (&$count, $randInt) {
            $count++;
            throw new Exception("thrown from here $randInt");
        };

        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(5)
                ->exceptionCallback($callback)
                ->retryExceptions()
                ->attempt(fn() => throw new Exception(), null);
        } catch (Throwable $e) {
        }

        self::assertInstanceOf(Exception::class, $e);
        self::assertSame("thrown from here $randInt", $e->getMessage());
        self::assertSame(1, $count);
    }
}
