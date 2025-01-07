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
 * Test the BackoffRunnerTrait - test "success" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksSuccessUnitTest extends PHPUnitTestCase
{
    /**
     * Test that different combinations of success callbacks are called successfully.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_success_callback_is_called()
    {
        $maxAttempts = 5;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts);

        $createCallback = fn(int &$count) => function () use (&$count) {
            $count++;
        };



        // callback1
        $count1 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);

        // callback1, callback2
        $count1 = $count2 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);

        // callback1, [callback2, callback3]
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);

        // callback1
        // [callback2, callback3, callback4]
        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->successCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);
        self::assertSame(1, $count4);

        // callback1
        // callback2
        // callback3
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->successCallback($createCallback($count2))
            ->successCallback($createCallback($count3))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);



        // a callable array. i.e. [class, method]
        $counter = new CounterClass();
        $callable = [$counter, 'increment'];

        $newBackoff()
            ->successCallback($callable)
            ->attempt(fn() => true, null);
        self::assertSame(1, $counter->getCount());
    }

    /**
     * Test that success callbacks are called only once when expected, even if exceptions or invalid values have caused
     * multiple retries.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_success_callbacks_are_called_only_once_when_expected(): void
    {
        $createCallback = fn(int &$count) => function () use (&$count) {
            $count++;
        };

        // generate a callback that throws a callback until it's been called a certain number of times
        $succeedAfterXCallback = function ($count) {
            $current = 0;
            return function () use (&$current, $count) {
                $current++;
                if ($current < $count) {
                    throw new Exception();
                }
            };
        };

        foreach ([0, 1, 5] as $maxAttempts) {
            foreach ([1, 2, 4, 5, 6] as $succeedAfter) {

                $count1 = 0;
                Backoff::noop()
                    ->maxAttempts($maxAttempts)
                    ->successCallback($createCallback($count1))
                    ->attempt($succeedAfterXCallback($succeedAfter), null);

                $expectedCount = ($maxAttempts > 0) && ($succeedAfter <= $maxAttempts) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<
            }
        }
    }

    /**
     * Test the parameters that can be passed to success callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_parameters_passed_to_success_callbacks(): void
    {
        // generate a callback that throws an exception until it's been called a certain number of times
        $succeedAfterXCallback = function ($count, $successResult) {
            $current = 0;
            return function () use (&$current, $count, $successResult) {
                $current++;
                if ($current < $count) {
                    throw new Exception();
                }
                return $successResult;
            };
        };

        $createCallback = function (
            int $succeedAfter,
            int $intendedResult,
            ?array &$passedLogs,
        ) {
            return function (
                $result,
                AttemptLog $attemptLog,
                $log,
                $logs,
            ) use (
                $succeedAfter,
                $intendedResult,
                &$passedLogs,
            ) {
                // check that $result was passed correctly
                self::assertSame($intendedResult, $result);

                // check that current AttemptLogs was passed correctly
                self::assertInstanceOf(AttemptLog::class, $attemptLog);
                self::assertSame($attemptLog, $log);
                self::assertSame($succeedAfter, $attemptLog->attemptNumber());
                self::assertSame(10, $attemptLog->maxAttempts());

                // check that $logs was passed correctly
                self::assertIsArray($logs);
                self::assertCount($succeedAfter, $logs);
                foreach ($logs as $log2) {
                    self::assertInstanceOf(AttemptLog::class, $log2);
                    self::assertSame(10, $log2->maxAttempts());
                }

                $passedLogs = $logs;
            };
        };

        foreach ([1, 2, 3, 4] as $succeedAfter) {

            $intendedResult = mt_rand(0, 10);
            $passedLogs = null;

            $backoff = Backoff::noop()
                ->maxAttempts(10)
                ->successCallback($createCallback($succeedAfter, $intendedResult, $passedLogs));
            $backoff->attempt($succeedAfterXCallback($succeedAfter, $intendedResult), null);

            self::assertSame($backoff->logs(), $passedLogs);
        }
    }

    /**
     * Test that success callbacks aren't called when they have arguments that don't match.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_success_callbacks_arent_called_when_their_arguments_dont_match(): void
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
            ->successCallback($callback1, $callback2)
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(0, $count2);
    }

    /**
     * Test that exceptions thrown by success callbacks are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_success_callback_exceptions_are_thrown(): void
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
                ->successCallback($callback)
                ->attempt(fn() => true, null);
        } catch (Throwable $e) {
        }

        self::assertInstanceOf(Exception::class, $e);
        self::assertSame($exception, $e);
        self::assertSame(1, $count);
    }
}
