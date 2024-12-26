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
 * Test the BackoffRunnerTrait - test "failure" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksFailureUnitTest extends PHPUnitTestCase
{
    /**
     * Test that different combinations of failure callbacks are called successfully.
     *
     * Also tests that fallbackCallback() is an alias for failureCallback().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_failure_callback_is_called()
    {
        $maxAttempts = 5;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts);

        $createCallback = fn(int &$count) => function () use (&$count) {
            $count++;
        };

        foreach (['failureCallback', 'fallbackCallback'] as $method) {

            // callback1
            $count1 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);

            // callback1, callback2
            $count1 = $count2 = 0;
            $newBackoff()
                ->$method($createCallback($count1), $createCallback($count2))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);

            // callback1, [callback2, callback3]
            $count1 = $count2 = $count3 = 0;
            $newBackoff()
                ->$method($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);

            // callback1
            // [callback2, callback3, callback4]
            $count1 = $count2 = $count3 = $count4 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->$method([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);
            self::assertSame(1, $count4);

            // callback1
            // callback2
            // callback3
            $count1 = $count2 = $count3 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->$method($createCallback($count2))
                ->$method($createCallback($count3))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);



            // a callable array. i.e. [class, method]
            $counter = new CounterClass();
            $callable = [$counter, 'increment'];

            $newBackoff()
                ->$method($callable)
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $counter->getCount());
        }
    }

    /**
     * Test that the failure callback is called only once when expected, even if exceptions or invalid values have
     * caused multiple retries.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_failure_callbacks_are_called_only_once_when_expected(): void
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

                // when a default value is returned
                $count1 = 0;
                Backoff::noop()
                    ->retryExceptions()
                    ->maxAttempts($maxAttempts)
                    ->failureCallback($createCallback($count1))
                    ->attempt($succeedAfterXCallback($succeedAfter), null); // <<< default

                $expectedCount = (($maxAttempts <= 0) || ($succeedAfter > $maxAttempts)) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<

                // even when the exception is rethrown
                $count1 = 0;
                try {
                    Backoff::noop()
                        ->retryExceptions()
                        ->maxAttempts($maxAttempts)
                        ->failureCallback($createCallback($count1))
                        ->attempt($succeedAfterXCallback($succeedAfter)); // <<< no default (exception rethrown)
                } catch (Throwable) {
                }
                $expectedCount = (($maxAttempts <= 0) || ($succeedAfter > $maxAttempts)) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<
            }
        }
    }

    /**
     * Test the parameters that can be passed to failure callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_parameters_passed_to_failure_callbacks(): void
    {
        $createCallback = function (
            int $maxAttempts,
            ?array &$passedLogs,
        ) {
            return function (
                AttemptLog $attemptLog,
                $log,
                $logs,
            ) use (
                $maxAttempts,
                &$passedLogs,
            ) {
                // check that current AttemptLogs was passed correctly
                self::assertInstanceOf(AttemptLog::class, $attemptLog);
                self::assertSame($attemptLog, $log);
                self::assertSame($maxAttempts, $attemptLog->attemptNumber());
                self::assertSame($maxAttempts, $attemptLog->maxAttempts());

                // check that $logs was passed correctly
                self::assertIsArray($logs);
                self::assertCount($maxAttempts, $logs);
                foreach ($logs as $log2) {
                    self::assertInstanceOf(AttemptLog::class, $log2);
                    self::assertSame($maxAttempts, $log2->maxAttempts());
                }

                $passedLogs = $logs;
            };
        };

        foreach ([1, 2, 3, 4] as $maxAttempts) {

            $passedLogs = null;

            $backoff = Backoff::noop()
                ->retryExceptions()
                ->maxAttempts($maxAttempts)
                ->failureCallback($createCallback($maxAttempts, $passedLogs));
            $backoff->attempt(fn() => throw new Exception(), null);

            self::assertSame($backoff->logs(), $passedLogs);
        }
    }

    /**
     * Test that failure callbacks aren't called when they have arguments that don't match.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_failure_callbacks_arent_called_when_their_arguments_dont_match(): void
    {
        $count1 = 0;
        $callback1 = function ($log) use (&$count1) {
            $count1++;
        };
        $count2 = 0;
        $callback2 = function ($log, int $int) use (&$count2) {
            $count2++;
        };

        Backoff::noop()
            ->maxAttempts(1)
            ->failureCallback($callback1, $callback2)
            ->attempt(fn() => throw new Exception(), true);
        self::assertSame(1, $count1);
        self::assertSame(0, $count2);
    }

    /**
     * Test that exceptions thrown by failure callbacks are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_failure_callback_exceptions_are_thrown(): void
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
                ->maxAttempts(1)
                ->failureCallback($callback)
                ->attempt(fn() => throw new Exception(), true);
        } catch (Throwable $e) {
        }

        self::assertInstanceOf(Exception::class, $e);
        self::assertSame($exception, $e);
        self::assertSame(1, $count);
    }
}
