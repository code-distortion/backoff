<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\CounterClass;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * Test the BackoffRunnerTrait - test "invalid-result" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksInvalidResultUnitTest extends PHPUnitTestCase
{
    /**
     * Test that different combinations of invalid-result callbacks are called successfully.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_invalid_result_callback_is_called(): void
    {
        $maxAttempts = 2;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts);
        $createCallback = fn(int &$count) => function () use (&$count) {
            $count++;
        };



        // callback1
        $count1 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);

        // callback1, callback2
        $count1 = $count2 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);

        // callback1, [callback2, callback3]
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $count1);
        self::assertSame($maxAttempts, $count2);
        self::assertSame($maxAttempts, $count3);

        // callback1
        // [callback2, callback3, callback4]
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

        // callback1
        // callback2
        // callback3
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



        // a callable array. i.e. [class, method]
        $counter = new CounterClass();
        $callable = [$counter, 'increment'];

        $count1 = $count2 = 0;
        $newBackoff()
            ->retryWhen(10)
            ->invalidResultCallback($callable)
            ->attempt(fn() => 10, null);
        self::assertSame($maxAttempts, $counter->getCount());



        // triggered via ->retryUntil()
        // callback1
        // callback2
        // callback3
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
     * Test the parameters that can be passed to invalid-result callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_parameters_passed_to_invalid_result_callbacks(): void
    {
        $maxAttempts = mt_rand(1, 10);
        $newNoopBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts);
        $newSequenceBackoff = fn() => Backoff::sequenceUs([1, 2, 3]);
        $intendedResult = mt_rand(0, 10);

        $createCallback = function (
            int &$count,
            bool $expectedWillRetry,
            int $maxAttempts,
            int $actualMaxAttempts,
            int $intendedResult,
            ?array &$passedLogs,
        ) {
            return function (
                $result,
                bool $willRetry,
                AttemptLog $attemptLog,
                $log,
                $logs,
            ) use (
                &$count,
                $maxAttempts,
                $expectedWillRetry,
                $actualMaxAttempts,
                $intendedResult,
                &$passedLogs,
            ) {
                // check that $result was passed correctly
                self::assertSame($intendedResult, $result);

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

        // noop - WILL retry because of an invalid return value
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newNoopBackoff()
            ->retryUntil(true, true)
            ->invalidResultCallback($createCallback(
                $count1,
                true,
                $maxAttempts,
                $maxAttempts,
                $intendedResult,
                $passedLogs,
            ));
        $backoff->attempt(fn() => $intendedResult, null);
        self::assertSame($backoff->logs(), $passedLogs);

        // sequence (which has 3 retries) - WILL retry because of an invalid return value
        $count1 = 0;
        $passedLogs = null;
        $backoff = $newSequenceBackoff()
            ->retryUntil(true, true)
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->invalidResultCallback($createCallback(
                $count1,
                true,
                6,
                4,
                $intendedResult,
                $passedLogs,
            ));
        $backoff->attempt(fn() => $intendedResult, null);
        self::assertSame($backoff->logs(), $passedLogs);
    }

    /**
     * Test that invalid-result callbacks aren't called when they have arguments that don't match.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_invalid_result_callbacks_arent_called_when_their_arguments_dont_match(): void
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
            ->retryWhen(10)
            ->invalidResultCallback($callback1, $callback2)
            ->attempt(fn() => 10, null);
        self::assertSame(1, $count1);
        self::assertSame(0, $count2);
    }

    /**
     * Test that exceptions thrown by invalid-result callbacks are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_invalid_result_callback_exceptions_are_thrown(): void
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
                ->retryWhen(10)
                ->invalidResultCallback($callback)
                ->attempt(fn() => 10, null);
        } catch (Throwable $e) {
        }

        self::assertInstanceOf(Exception::class, $e);
        self::assertSame($exception, $e);
        self::assertSame(1, $count);
    }
}
