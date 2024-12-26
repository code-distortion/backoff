<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\CounterClass;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn1;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn2;
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
     * Test that different combinations of exception callbacks are called successfully.
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
        $throwException = new Exception();
        $createCallback = fn(int &$count) => function () use (&$count) {
            $count++;
        };



        // callback1
        $count1 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $count1);

        // callback1, callback2
        $count1 = $count2 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);

        // callback1, [callback2, callback3]
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);

        // callback1
        // [callback2, callback3, callback4]
        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->exceptionCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);
        self::assertSame($maxAttempts, $count4);

        // callback1
        // callback2
        // callback3
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count1))
            ->exceptionCallback($createCallback($count2))
            ->exceptionCallback($createCallback($count3))
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);



        // a callable array. i.e. [class, method]
        $counter = new CounterClass();
        $callable = [$counter, 'increment'];

        $count1 = $count2 = 0;
        $newBackoff()
            ->exceptionCallback($callable)
            ->attempt(fn() => throw $throwException, null);
        self::assertSame($maxAttempts, $counter->getCount());
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

        $throwException = new OtherExcptn1();
        $createCallback = fn(int &$count)
            => function (
                $e,
                $exception,
                Throwable $blah,
                OtherExcptn1 $otherExcptn1,
                ?OtherExcptn2 $otherExcptn2,
            ) use (
                &$count,
                $throwException,
            ) {
                $count++;
                self::assertSame($throwException, $e);
                self::assertSame($throwException, $exception);
                self::assertSame($throwException, $blah);
                self::assertSame($throwException, $otherExcptn1);
                self::assertNull($otherExcptn2);
            };

        $count = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count))
            ->attempt(fn() => throw $throwException, null);
        self::assertGreaterThan(0, $count); // just confirm that it happened
    }

    /**
     * Test the parameters that can be passed to exception callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_parameters_passed_to_exception_callbacks(): void
    {
        $maxAttempts = mt_rand(1, 10);
        $newNoopBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();
        $newSequenceBackoff = fn() => Backoff::sequenceUs([1, 2, 3])->retryExceptions();

        $throwException = new Exception();
        $createCallback = function (
            int &$count,
            bool $expectedWillRetry,
            int $maxAttempts,
            int $actualMaxAttempts,
            ?array &$passedLogs
        ) use ($throwException) {
            return function (
                $e,
                $exception,
                Throwable $blah,
                bool $willRetry,
                AttemptLog $attemptLog,
                $log,
                $logs,
            ) use (
                &$count,
                $maxAttempts,
                $expectedWillRetry,
                $actualMaxAttempts,
                $throwException,
                &$passedLogs,
            ) {
                // check the exception was passed correctly
                self::assertSame($throwException, $e);
                self::assertSame($throwException, $exception);
                self::assertSame($throwException, $blah);

                // check that $willRetry was passed correctly
                $count++;
                $expectedWillRetry = ($count < $actualMaxAttempts) // use $expectedWillRetry, until the last attempt
                    ? $expectedWillRetry
                    : false;
                self::assertSame($expectedWillRetry, $willRetry); // <<<

                // check that current AttemptLogs was passed correctly
                self::assertInstanceOf(AttemptLog::class, $attemptLog);
                self::assertSame($attemptLog, $log);
                self::assertSame($count, $attemptLog->attemptNumber());
                self::assertSame($maxAttempts, $attemptLog->maxAttempts());

                // check that $logs was passed correctly
                self::assertIsArray($logs);
                self::assertCount($count, $logs);
                foreach ($logs as $log2) {
                    self::assertInstanceOf(AttemptLog::class, $log2);
                    self::assertSame($maxAttempts, $log2->maxAttempts());
                }

                $passedLogs = $logs;
            };
        };

        // noop - WILL retry exceptions
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newNoopBackoff()
            ->exceptionCallback($createCallback($count1, true, $maxAttempts, $maxAttempts, $passedLogs));
        $backoff->attempt(fn() => throw $throwException, null);
        self::assertSame($backoff->logs(), $passedLogs);

        // noop - will NOT retry exceptions
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newNoopBackoff()
            ->dontRetryExceptions()
            ->exceptionCallback($createCallback($count1, false, $maxAttempts, 1, $passedLogs));
        $backoff->attempt(fn() => throw $throwException, null);
        self::assertSame($backoff->logs(), $passedLogs);

        // sequence (which has 3 retries) - WILL retry exceptions
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newSequenceBackoff()
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->exceptionCallback($createCallback($count1, true, 6, 4, $passedLogs));
        $backoff->attempt(fn() => throw $throwException, null);
        self::assertSame($backoff->logs(), $passedLogs);

        // sequence (which has 3 retries) - will NOT retry exceptions
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newSequenceBackoff()
            ->dontRetryExceptions()
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->exceptionCallback($createCallback($count1, false, 6, 1, $passedLogs));
        $backoff->attempt(fn() => throw $throwException, null);
        self::assertSame($backoff->logs(), $passedLogs);
    }

    /**
     * Test that exception callbacks aren't called when they have arguments that don't match.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_exception_callbacks_arent_called_when_their_arguments_dont_match(): void
    {
        $count1 = 0;
        $callback1 = function ($log) use (&$count1) {
            $count1++;
        };
        $count2 = 0;
        $callback2 = function ($log, int $int) use (&$count2) {
            $count2++;
        };

        Backoff::noop()->maxAttempts(1)
            ->exceptionCallback($callback1, $callback2)
            ->attempt(fn() => throw new Exception(), null);
        self::assertSame(1, $count1);
        self::assertSame(0, $count2);
    }

    /**
     * Test that exceptions thrown by exception-callbacks are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_exception_callback_exceptions_are_rethrown(): void
    {
        $exception = new Exception();
        $count = 0;
        $callback = function () use (&$count, $exception) {
            $count++;
            throw $exception;
        };

        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(5)
                ->retryExceptions()
                ->exceptionCallback($callback)
                ->attempt(fn() => throw new Exception(), null);
        } catch (Throwable $e) {
        }

        self::assertInstanceOf(Exception::class, $e);
        self::assertSame($exception, $e);
        self::assertSame(1, $count);
    }
}
