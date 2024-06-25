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
 * Test the BackoffRunnerTrait - test "failure" callbacks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitCallbacksFailureUnitTest extends PHPUnitTestCase
{
    /**
     * Test that different combinations of failureCallbacks are called.
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
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();

        $createCallback = fn(&$count)
            /** @var AttemptLog[] $logs */
            => function (array $logs) use (&$count) {
                $count++;
            };

        foreach (['failureCallback', 'fallbackCallback'] as $method) {

            // one callback
            $count1 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);

            // two callbacks, separate params
            $count1 = $count2 = 0;
            $newBackoff()
                ->$method($createCallback($count1), $createCallback($count2))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);

            // three callbacks, separate params, two in an array
            $count1 = $count2 = $count3 = 0;
            $newBackoff()
                ->$method($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);

            // four callbacks, two calls, including an array
            $count1 = $count2 = $count3 = $count4 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->$method([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);
            self::assertSame(1, $count4);

            // three callbacks, separate calls
            $count1 = $count2 = $count3 = 0;
            $newBackoff()
                ->$method($createCallback($count1))
                ->$method($createCallback($count2))
                ->$method($createCallback($count3))
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $count1);
            self::assertSame(1, $count2);
            self::assertSame(1, $count3);

            // one callback as a callable array
            $invokableClass = new InvokableClass();
            $callable = [$invokableClass, '__invoke'];

            $newBackoff()
                ->$method($callable)
                ->attempt(fn() => throw new Exception(), null);
            self::assertSame(1, $invokableClass->getCount());
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
        $createCallback = fn(&$count)
            /** @var AttemptLog[] $logs */
            => function (array $logs) use (&$count) {
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
            foreach ([ 1, 2, 4, 5, 6] as $succeedAfter) {

                // when a default value is returned
                $count1 = 0;
                Backoff::noop()
                    ->retryExceptions()
                    ->maxAttempts($maxAttempts)
                    ->failureCallback($createCallback($count1))
                    ->attempt($succeedAfterXCallback($succeedAfter), null);

                $expectedCount = ($maxAttempts <= 0) || ($succeedAfter > $maxAttempts) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<

                // even when the exception is rethrown
                $count1 = 0;
                try {
                    Backoff::noop()
                        ->retryExceptions()
                        ->maxAttempts($maxAttempts)
                        ->failureCallback($createCallback($count1))
                        ->attempt($succeedAfterXCallback($succeedAfter));
                } catch (Throwable) {
                }
                $expectedCount = ($maxAttempts <= 0) || ($succeedAfter > $maxAttempts) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<
            }
        }
    }

    /**
     * Test that the array of AttemptLogs are passed to failure callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_attempt_logs_are_passed_to_failure_callbacks(): void
    {
        foreach ([0, 1, 2, 3, 4] as $maxAttempts) {

            $passedLogs = null;
            $callback = function (array $logs) use (&$passedLogs) {
                /** @var AttemptLog[] $logs */
                $passedLogs = $logs;
            };

            $backoff = Backoff::noop()
                ->retryExceptions()
                ->maxAttempts($maxAttempts)
                ->failureCallback($callback);
            $backoff->attempt(fn() => throw new Exception(), null);

            self::assertSame($backoff->logs(), $passedLogs);
        }
    }
}
