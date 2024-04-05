<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\InvokableClass;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn1;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn2;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn3;
use DateTime;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Throwable;

/**
 * Test the BackoffRunnerTrait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitUnitTest extends PHPUnitTestCase
{
    /**
     * Test that Backoff catches exceptions and retries because of them.
     *
     * @test
     * @dataProvider backoffRetryExceptionsDataProvider
     *
     * @param callable                                                      $attempt             The callback to
     *                                                                                           attempt.
     * @param boolean                                                       $resetCatchAllExcptn Whether to start by
     *                                                                                           resetting the default
     *                                                                                           catch-all exceptions,
     *                                                                                           so none are caught
     *                                                                                           initially.
     * @param class-string|callable|array<class-string|callable>|false|null $retryExceptions1    The exceptions to
     *                                                                                           retry on (if present).
     * @param class-string|callable|array<class-string|callable>|false|null $retryExceptions2    The exceptions to retry
     *                                                                                           on (allowing another
     *                                                                                           call if present).
     * @param integer                                                       $expectedAttempts    The number of attempts
     *                                                                                           expected.
     * @param mixed                                                         $expectedResult      The expected return
     *                                                                                           value.
     * @param Throwable|null                                                $expectedException   The expected exception
     *                                                                                           to be caught.
     * @param boolean                                                       $expectRuntimeExcptn Whether a
     *                                                                                           BackoffRuntimeException
     *                                                                                           is expected (internal
     *                                                                                           error).
     * @return void
     */
    #[Test]
    #[DataProvider('backoffRetryExceptionsDataProvider')]
    public static function test_that_backoff_catches_exceptions_and_retries_because_of_them(
        callable $attempt,
        bool $resetCatchAllExcptn,
        string|callable|array|false|null $retryExceptions1,
        string|callable|array|false|null $retryExceptions2,
        int $expectedAttempts,
        mixed $expectedResult,
        ?Throwable $expectedException,
        bool $expectRuntimeExcptn,
    ): void {

        $count = 0;
        $wrappedAttempt = function () use (&$count, $attempt) {
            $count++;
            return $attempt();
        };

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(5);

        if ($resetCatchAllExcptn) {
            $backoff->dontRetryExceptions();
        }

        if (!is_null($retryExceptions1)) {
            $backoff->retryExceptions($retryExceptions1);
        }
        if (!is_null($retryExceptions2)) {
            $backoff->retryExceptions($retryExceptions2);
        }

        // use the backoff to attempt the callback
        $result = null;
        $caughtException = null;
        $caughtRuntimeException = false;
        try {
            $result = $backoff->attempt($wrappedAttempt);
        } catch (BackoffRuntimeException) {
            $caughtRuntimeException = true;
        } catch (Throwable $e) {
            $caughtException = $e;
        }

        self::assertSame($expectedAttempts, $count);
        self::assertSame($expectedResult, $result);
        if ($expectRuntimeExcptn) {
            self::assertTrue($caughtRuntimeException);
        } else {
            self::assertSame($expectedException, $caughtException);
            self::assertFalse($caughtRuntimeException);
        }
    }

    /**
     * DataProvider for test_that_backoff_catches_exceptions_and_retries_because_of_them.
     *
     * @return array<string,array<string,callable|boolean|class-string|array<class-string|callable>|boolean|null|int|Throwable|boolean>>
     * @throws Exception Doesn't actually throw this, however phpcs expects it.
     */
    public static function backoffRetryExceptionsDataProvider(): array
    {
        $randInt = mt_rand();
        $throwUntilAttempt = function ($throwUntil, $return, $throw) {
            return function () use (&$throwUntil, $return, $throw): mixed {
                return --$throwUntil <= 0
                    ? $return
                    : throw $throw;
            };
        };
        $backoffException = new BackoffException();
        $regularException = new Exception();
        $retryExceptionsCallback = fn($return) => (fn(Throwable $e, AttemptLog $attemptLog) => $return);

        return [

            // successful attempts

            'successful attempt - no catch' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch false' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => false,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch all' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => false, // <<< don't reset so none are caught, just use the default
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch all 2' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch OtherExcptn1' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch BackoffException' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => BackoffException::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch via callback returning true' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(true),
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt - catch via callback returning false' => [
                'attempt' => fn() => $randInt,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(false),
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],



            // unsuccessful attempts

            'unsuccessful attempts - no catch' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch false' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => false,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch all' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => false, // <<< don't reset so none are caught, just use the default
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch all 2' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => BackoffException::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch via callback returning true' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(true),
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch via callback returning false' => [
                'attempt' => fn() => throw $regularException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(false),
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $regularException,
                'expectRuntimeExcptn' => false,
            ],



            // successful attempts after 3 tries

            'successful attempt after 3 - no catch' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch false' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => false,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch all' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => false, // <<< don't reset so none are caught, just use the default
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch all 2' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch OtherExcptn1' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch BackoffException' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => BackoffException::class,
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch via callback returning true' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(true),
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedResult' => $randInt,
                'expectedException' => null,
                'expectRuntimeExcptn' => false,
            ],
            'successful attempt after 3 - catch via callback returning false' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $regularException),
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => $retryExceptionsCallback(false),
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $regularException,
                'expectRuntimeExcptn' => false,
            ],



            // unsuccessful attempts
            // catch multiple things

            'unsuccessful attempts - catch OtherExcptn1 and OtherExcptn2' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, OtherExcptn2::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1 and BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],

            // catch defined with an array

            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2 and OtherExcptn2' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, OtherExcptn2::class, OtherExcptn3::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2 and BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, OtherExcptn2::class, BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2 and callback returning false' => [
                'attempt' => fn() => throw $regularException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, OtherExcptn2::class, $retryExceptionsCallback(false)],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $regularException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2 and callback returning true' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => [OtherExcptn1::class, OtherExcptn2::class, $retryExceptionsCallback(true)],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],



            // test that calling multiple ->retryExceptions() multiple times adds to the list to check

            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => [OtherExcptn2::class],
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1, OtherExcptn2 and BackoffException, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => [OtherExcptn2::class, BackoffException::class],
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch BackoffException, OtherExcptn1, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => BackoffException::class,
                'retryExceptions2' => OtherExcptn1::class,
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch OtherExcptn1, then all, diff times' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => OtherExcptn1::class,
                'retryExceptions2' => [], // <<< catch all exceptions again
                'expectedAttempts' => 5,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
            'unsuccessful attempts - catch BackoffException, then false, diff times' => [
                'attempt' => fn() => throw $backoffException,
                'resetCatchAllExcptn' => true,
                'retryExceptions1' => BackoffException::class,
                'retryExceptions2' => false, // <<< reset and don't catch exceptions
                'expectedAttempts' => 1,
                'expectedResult' => null,
                'expectedException' => $backoffException,
                'expectRuntimeExcptn' => false,
            ],
        ];
    }





    /**
     * Test that Backoff checks for invalid "retry when" values, and retries because of them.
     *
     * @test
     * @dataProvider backoffRetryWhenResponseDataProvider
     *
     * @param callable   $attempt              The callback to attempt.
     * @param mixed|null $invalidValues1       The exceptions to retry on (if present).
     * @param boolean    $invalidValues1Strict Whether the 1st values should be checked strictly.
     * @param mixed|null $invalidValues2       The exceptions to retry on (allowing another call if present).
     * @param boolean    $invalidValues2Strict Whether the 2nd values should be checked strictly.
     * @param integer    $expectedAttempts     The number of attempts expected.
     * @param mixed      $expectedResult       The expected return value.
     * @return void
     */
    #[Test]
    #[DataProvider('backoffRetryWhenResponseDataProvider')]
    public static function test_that_backoff_checks_for_invalid_retry_when_values_and_retries_because_of_them(
        callable $attempt,
        mixed $invalidValues1,
        bool $invalidValues1Strict,
        mixed $invalidValues2,
        bool $invalidValues2Strict,
        int $expectedAttempts,
        mixed $expectedResult,
    ): void {

        $count = 0;
        $wrappedAttempt = function () use (&$count, $attempt) {
            $count++;
            return $attempt();
        };

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(5);

        if (!is_null($invalidValues1)) {
            $backoff->retryWhen($invalidValues1, $invalidValues1Strict);
        }
        if (!is_null($invalidValues2)) {
            $backoff->retryWhen($invalidValues2, $invalidValues2Strict);
        }

        // use the backoff to attempt the callback
        $result = $backoff->attempt($wrappedAttempt);

        self::assertSame($expectedAttempts, $count);
        self::assertSame($expectedResult, $result);
    }

    /**
     * DataProvider for test_that_backoff_check_for_invalid_values_and_retries_because_of_them.
     *
     * @return array<string,array<string,callable|integer|boolean|null|mixed>>
     */
    public static function backoffRetryWhenResponseDataProvider(): array
    {
        $randInt1 = mt_rand();

        $default = [
            'attempt' => fn() => $randInt1,
            'invalidValues1' => null,
            'invalidValues1Strict' => false,
            'invalidValues2' => null,
            'invalidValues2Strict' => false,
            'expectedAttempts' => 1,
            'expectedResult' => $randInt1,
        ];

        $return = [

            // successful attempts

            'successful attempt' => [],

            // not strict
            'successful attempt - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'expectedAttempts' => 1,
            ],
            'successful attempt - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues2' => $randInt1 - 1,
                'expectedAttempts' => 1,
            ],
            'successful attempt - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1,
                'expectedAttempts' => 5,
            ],
            'successful attempt - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues2' => $randInt1,
                'expectedAttempts' => 5,
            ],

            // strict
            'successful attempt (strict) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'invalidValues2' => $randInt1 - 1,
                'invalidValues2Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1,
                'invalidValues1Strict' => true,
                'expectedAttempts' => 5,
            ],
            'successful attempt (strict) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'invalidValues2' => $randInt1,
                'invalidValues2Strict' => true,
                'expectedAttempts' => 5,
            ],

            // strict 2
            'successful attempt (strict 2) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict 2) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'invalidValues2' => (float) $randInt1,
                'invalidValues2Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict 2) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (int) $randInt1,
                'invalidValues1Strict' => true,
                'expectedAttempts' => 5,
            ],
            'successful attempt (strict 2) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'invalidValues2' => (int) $randInt1,
                'invalidValues2Strict' => true,
                'expectedAttempts' => 5,
            ],

            // callback
            'successful attempt (callback) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => false,
                'expectedAttempts' => 1,
            ],
            'successful attempt (callback) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => false,
                'invalidValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedAttempts' => 1,
            ],
            'successful attempt (callback) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => true,
                'expectedAttempts' => 5,
            ],
            'successful attempt (callback) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => true,
                'invalidValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedAttempts' => 5,
            ],
        ];

        foreach ($return as $index => $value) {
            $return[$index] = array_merge($default, $value);
        }

        return $return;
    }





    /**
     * Test that Backoff checks for invalid "retry until" values, and retries because of them.
     *
     * @test
     * @dataProvider backoffRetryUntilResponseDataProvider
     *
     * @param callable   $attempt            The callback to attempt.
     * @param mixed|null $validValues1       The exceptions to retry until (if present).
     * @param boolean    $validValues1Strict Whether the 1st values should be checked strictly.
     * @param mixed|null $validValues2       The exceptions to retry until (allowing another call if present).
     * @param boolean    $validValues2Strict Whether the 2nd values should be checked strictly.
     * @param integer    $expectedAttempts   The number of attempts expected.
     * @param mixed      $expectedResult     The expected return value.
     * @return void
     */
    #[Test]
    #[DataProvider('backoffRetryUntilResponseDataProvider')]
    public static function test_that_backoff_checks_for_invalid_retry_until_values_and_retries_because_of_them(
        callable $attempt,
        mixed $validValues1,
        bool $validValues1Strict,
        mixed $validValues2,
        bool $validValues2Strict,
        int $expectedAttempts,
        mixed $expectedResult,
    ): void {

        $count = 0;
        $wrappedAttempt = function () use (&$count, $attempt) {
            $count++;
            return $attempt();
        };

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(5);

        if (!is_null($validValues1)) {
            $backoff->retryUntil($validValues1, $validValues1Strict);
        }
        if (!is_null($validValues2)) {
            $backoff->retryUntil($validValues2, $validValues2Strict);
        }

        // use the backoff to attempt the callback
        $result = $backoff->attempt($wrappedAttempt);

        self::assertSame($expectedAttempts, $count);
        self::assertSame($expectedResult, $result);
    }

    /**
     * DataProvider for test_that_backoff_check_for_invalid_values_and_retries_because_of_them.
     *
     * @return array<string,array<string,callable|integer|boolean|null|mixed>>
     */
    public static function backoffRetryUntilResponseDataProvider(): array
    {
        $randInt1 = mt_rand();

        $default = [
            'attempt' => fn() => $randInt1,
            'validValues1' => null,
            'validValues1Strict' => false,
            'validValues2' => null,
            'validValues2Strict' => false,
            'expectedAttempts' => 1,
            'expectedResult' => $randInt1,
        ];

        $return = [

            // successful attempts

            'successful attempt' => [],

            // not strict
            'successful attempt - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1,
                'expectedAttempts' => 1,
            ],
            'successful attempt - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues2' => $randInt1,
                'expectedAttempts' => 1,
            ],
            'successful attempt - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'expectedAttempts' => 5,
            ],
            'successful attempt - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 - 1,
                'validValues2' => $randInt1 + 1,
                'expectedAttempts' => 5,
            ],

            // strict
            'successful attempt (strict) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1,
                'validValues1Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues1Strict' => true,
                'validValues2' => $randInt1,
                'validValues2Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues1Strict' => true,
                'expectedAttempts' => 5,
            ],
            'successful attempt (strict) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 - 1,
                'validValues1Strict' => true,
                'validValues2' => $randInt1 + 1,
                'validValues2Strict' => true,
                'expectedAttempts' => 5,
            ],

            // strict 2
            'successful attempt (strict 2) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (int) $randInt1,
                'validValues1Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict 2) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (bool) $randInt1,
                'validValues1Strict' => true,
                'validValues2' => (int) $randInt1,
                'validValues2Strict' => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (strict 2) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (float) $randInt1,
                'validValues1Strict' => true,
                'expectedAttempts' => 5,
            ],
            'successful attempt (strict 2) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (bool) $randInt1,
                'validValues1Strict' => true,
                'validValues2' => (float) $randInt1,
                'validValues2Strict' => true,
                'expectedAttempts' => 5,
            ],

            // callback
            'successful attempt (callback) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => true,
                'expectedAttempts' => 1,
            ],
            'successful attempt (callback) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => true,
                'validValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedAttempts' => 1,
            ],
            'successful attempt (callback) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => false,
                'expectedAttempts' => 5,
            ],
            'successful attempt (callback) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => false,
                'validValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedAttempts' => 5,
            ],
        ];

        foreach ($return as $index => $value) {
            $return[$index] = array_merge($default, $value);
        }

        return $return;
    }





    /**
     * Test what Backoff returns - including when exceptions occur and invalid results are returned, and when default
     * values are specified in different places (exception, return and attempt default values).
     *
     * @test
     * @dataProvider backoffRethrowDataProvider
     *
     * @param callable       $attempt               The callback to attempt.
     * @param boolean|null   $checkForExceptions    The exception to retry on (if present).
     * @param boolean        $useExceptionDefault   Whether to use an "exception" default value or not.
     * @param mixed          $exceptionDefault      The default "exception" value to use.
     * @param boolean        $checkForInvalidValues Whether to check for invalid return values or not.
     * @param boolean        $useReturnDefault      Whether to use a "return" default value or not.
     * @param mixed          $returnDefault         The default "return" value to use.
     * @param boolean        $useAttemptDefault     Whether to use an "attempt" default value or not.
     * @param mixed          $attemptDefault        The default "attempt" value to use.
     * @param mixed          $expectedResult        The expected return value.
     * @param Throwable|null $expectedException     The expected exception to be caught.
     * @return void
     */
    #[Test]
    #[DataProvider('backoffRethrowDataProvider')]
    public static function test_what_backoff_returns(
        callable $attempt,
        ?bool $checkForExceptions,
        ?bool $useExceptionDefault,
        mixed $exceptionDefault,
        bool $checkForInvalidValues,
        ?bool $useReturnDefault,
        mixed $returnDefault,
        ?bool $useAttemptDefault,
        mixed $attemptDefault,
        mixed $expectedResult,
        ?Throwable $expectedException,
    ): void {

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(5);

        // use a default when an exception is thrown on the last attempt
        if ($checkForExceptions) {
            $useExceptionDefault
                ? $backoff->retryExceptions([], $exceptionDefault)
                : $backoff->retryExceptions([]);
        }

        // use a default when an invalid return value is given on the last attempt
        if ($checkForInvalidValues) {
            $useReturnDefault
                ? $backoff->retryWhen(false, false, $returnDefault)
                : $backoff->retryWhen(false);
        }

        // use the backoff to attempt the callback
        $result = null;
        $caughtException = null;
        try {

            $result = $useAttemptDefault
                ? $backoff->attempt($attempt, $attemptDefault)
                : $backoff->attempt($attempt);

        } catch (Throwable $e) {
            $caughtException = $e;
        }

        self::assertSame($expectedResult, $result);
        self::assertSame($expectedException, $caughtException);
    }

    /**
     * DataProvider for test_that_backoff_rethrows_the_last_exception.
     *
     * @return array<string,array<string,boolean|int|float|string|array<integer>|stdClass|callable>>
     * @throws Exception Doesn't actually throw this, however phpcs expects it.
     */
    public static function backoffRethrowDataProvider(): array
    {
        foreach (['bool', 'int', 'float', 'string', 'array', 'stdClass', 'callable'] as $type) {

            $successVal = self::generateRandomValueOfType($type);
            $exceptionVal = self::generateRandomValueOfType($type);
            $retryUntilVal = self::generateRandomValueOfType($type);
            $attemptVal = self::generateRandomValueOfType($type);

            $successReturn = $successVal; // when attempted callback returns a callable, it will be returned directly
            $exceptionReturn = is_callable($exceptionVal)
                ? $exceptionVal()
                : $exceptionVal;
            $retryUntilReturn = is_callable($retryUntilVal)
                ? $retryUntilVal()
                : $retryUntilVal;
            $attemptReturn = is_callable($attemptVal)
                ? $attemptVal()
                : $attemptVal;

            $backoffException = new BackoffException();

            $default = [
                'attempt' => fn() => null,
                'checkForExceptions' => false,
                'useExceptionDefault' => false,
                'exceptionDefault' => null,
                'checkForInvalidValues' => false,
                'useReturnDefault' => false,
                'returnDefault' => null,
                'useAttemptDefault' => false,
                'attemptDefault' => null,
                'expectedResult' => null,
                'expectedException' => null,
            ];

            $return = [

                // successful attempt

                // successful attempt: 0
                "successful attempt $type" => [
                    'attempt' => fn() => $successVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 1
                "successful attempt $type: (check for exp)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp + exp default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 2
                "successful attempt $type: (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 3
                "successful attempt $type: (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 1 + 2
                "successful attempt $type: (check for exp) (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 1 + 3
                "successful attempt $type: (check for exp) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp + exp default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 2 + 3
                "successful attempt $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],

                // successful attempt: 1 + 2 + 3
                "successful attempt $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: "
                . "(check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],
                "successful attempt $type: "
                . "(check for exp + exp default) (chk result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $successReturn,
                ],



                // throws exception

                // throws exception: 0
                "throws exception $type" => [
                    'attempt' => fn() => throw $backoffException,
                    'expectedException' => $backoffException,
                ],

                // throws exception: 1
                "throws exception $type: (check for exp)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check for exp + exp default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'expectedResult' => $exceptionReturn,
                ],

                // throws exception: 2
                "throws exception $type: (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedException' => $backoffException,
                ],

                // throws exception: 3
                "throws exception $type: (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],

                // throws exception: 1 + 2
                "throws exception $type: (check for exp) (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'expectedResult' => $exceptionReturn,
                ],
                "throws exception $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $exceptionReturn,
                ],

                // throws exception: 1 + 3
                "throws exception $type: (check for exp) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "throws exception $type: (check for exp + exp default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $exceptionReturn,
                ],

                // throws exception: 2 + 3
                "throws exception $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "throws exception $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],

                // throws exception: 1 + 2 + 3
                "throws exception $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "throws exception $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $exceptionReturn,
                ],
                "throws exception $type: (check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "throws exception $type: "
                . "(check for exp + exp default) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $exceptionReturn,
                ],



                // invalid result

                // invalid result: 0
//                "invalid result $type" => [
//                    'attempt' => fn() => false,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 1
//                "invalid result $type: (check for exp)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => false,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],
//                "invalid result $type: (check for exp + exp default)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => true,
//                    'exceptionDefault' => $exceptionVal,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 2
                "invalid result $type: (check result)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $retryUntilReturn,
                ],

                // invalid result: 3
//                "invalid result $type: (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptVal,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 1 + 2
                "invalid result $type: (check for exp) (check result)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $retryUntilReturn,
                ],
                "invalid result $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'expectedResult' => $retryUntilReturn,
                ],

                // invalid result: 1 + 3
//                "invalid result $type: (check for exp) (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => false,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptVal,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],
//                "invalid result $type: (check for exp + exp default) (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => true,
//                    'exceptionDefault' => $exceptionVal,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptVal,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 2 + 3
                "invalid result $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "invalid result $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $retryUntilReturn,
                ],

                // invalid result: 1 + 2 + 3
                "invalid result $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "invalid result $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $attemptReturn,
                ],
                "invalid result $type: (check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $retryUntilReturn,
                ],
                "invalid result $type: "
                . "(check for exp + exp default) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionVal,
                    'checkForInvalidValues' => true,
                    'useReturnDefault' => true,
                    'returnDefault' => $retryUntilVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptVal,
                    'expectedResult' => $retryUntilReturn,
                ],
            ];
        }

        foreach ($return as $index => $value) {
            $return[$index] = array_merge($default, $value);
        }

        return $return;
    }

    /**
     * Generate a random value of the given type.
     *
     * @param string $type The type of value to generate.
     * @return mixed
     */
    private static function generateRandomValueOfType(string $type): mixed
    {
        $callableRand = mt_rand();

        /** @var 'bool'|'int'|'float'|'string'|'array'|'stdClass'|'callable' $type */
        return match ($type) {
            'bool' => (bool) mt_rand(0, 1),
            'int' => mt_rand(),
            'float' => mt_rand(1, 100) / mt_rand(1, 100),
            'string' => md5((string) mt_rand()),
            'array' => [mt_rand()],
            'stdClass' => (object) ['prop' => mt_rand()],
            'callable' => fn() => $callableRand,
        };
    }





    /**
     * Test that exceptions thrown by callbacks passed to ->retryExceptions(), are thrown.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_retry_exceptions_exceptions_are_thrown(): void
    {
        $exception2 = new Exception('exception 2');
        $count = 0;
        $callback = function (Throwable $e, AttemptLog $log) use (&$count, $exception2): bool {
            $count++;
            throw $exception2;
        };

        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(2)
                ->retryExceptions($callback)
                ->attempt(fn() => throw new Exception('exception 1'));
        } catch (Throwable $e) {
        }

        self::assertSame(1, $count);
        self::assertSame($exception2, $e);
    }





    /**
     * Test the retryAllExceptions() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_retry_all_exceptions(): void
    {
        $randInt = mt_rand();

        $count = 0;
        $exception = new Exception();
        $callback = function () use (&$count, $exception) {
            $count++;

            throw $exception;
        };



        // pass a default
        $result = Backoff::noop()
            ->maxAttempts(2)
            ->retryExceptions(OtherExcptn1::class) // start with this
            ->retryAllExceptions($randInt) // but reset to this
            ->attempt(fn() => throw new Exception());

        self::assertSame($randInt, $result);



        // pass no default
        $count = 0;
        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(2)
                ->retryExceptions(OtherExcptn1::class) // start with this
                ->retryAllExceptions() // but reset to this
                ->attempt($callback);
        } catch (Throwable $e) {
        }

        self::assertSame(2, $count);
        self::assertSame($exception, $e);



        // pass no default
        $count = 0;
        $e = null;
        try {
            Backoff::noop()
                ->maxAttempts(2)
                ->retryExceptions(Exception::class, 'default') // start with this
                ->retryAllExceptions() // but reset to this
                ->attempt($callback);
        } catch (Throwable $e) {
        }

        self::assertSame(2, $count);
        self::assertSame($exception, $e);
    }

    /**
     * Test that exceptions are not retried when ->dontRetryExceptions() is called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_dont_retry_exceptions_disables_retries(): void
    {
        $newBackoff = fn() => Backoff::noop()->maxAttempts(5)->retryExceptions();

        $createCallback = fn(&$count) => function (Throwable $e, AttemptLog $log, bool $willRetry) use (&$count) {
            $count++;
        };

        // noop - WILL retry exceptions - will call the callback more than once
        $count = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count))
            ->attempt(fn() => throw new Exception(), null);
        self::assertSame(5, $count); // <<<

        // noop - will NOT retry exceptions - will call the callback once
        $count = 0;
        $newBackoff()
            ->dontRetryExceptions()
            ->exceptionCallback($createCallback($count))
            ->attempt(fn() => throw new Exception(), null);
        self::assertSame(1, $count); // <<<
    }

    /**
     * Test that when exceptions are not retried, a default value can be used.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_dont_retry_exceptions_can_have_a_default(): void
    {
        $newBackoff = fn() => Backoff::noop()->maxAttempts(5)->retryExceptions();

        // with no default passed
        $caughtException = false;
        try {
            $newBackoff()
                ->dontRetryExceptions() // <<< no default
                ->attempt(fn() => throw new Exception());
        } catch (Throwable $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);

        // with default passed
        $default = mt_rand();
        $return = $newBackoff()
            ->dontRetryExceptions($default) // <<< default
            ->attempt(fn() => throw new Exception());
        self::assertSame($default, $return);
    }

    /**
     * Test that all exceptions are checked for and a default is returned when retryExceptions is called with only
     * default specified.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_exceptions_are_retried_when_only_a_default_is_passed(): void
    {
        $randInt = mt_rand();

        $result = Backoff::noop()
            ->maxAttempts(2)
            ->retryExceptions(OtherExcptn1::class) // start with this
            ->retryExceptions(default: $randInt) // but reset to this
            ->attempt(fn() => throw new Exception());

        self::assertSame($randInt, $result);
    }





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
        $newSequenceBackoff = fn() => Backoff::sequenceUs([1, 2, 3])->maxAttempts($maxAttempts)->retryExceptions();

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

        // sequence (with 3 retries) - WILL retry exceptions
        $count1 = 0;
        $newSequenceBackoff()
            ->maxAttempts(6) // more than the number of delays in the sequence
            ->exceptionCallback($createCallback($count1, true, 4))
            ->attempt(fn() => throw $exception, null);

        // sequence (with 3 retries) - will NOT retry exceptions
        $count1 = 0;
        $newSequenceBackoff()
            ->dontRetryExceptions() // more than the number of delays in the sequence
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





    /**
     * Test that ->retryWhen() and ->retryUntil() cancel each other out.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_retry_when_and_retry_until_reset_the_other(): void
    {
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
            return 10;
        };

        $backoff = Backoff::noop()->maxAttempts(5)->retryExceptions();
        $backoff->retryWhen(10);
        $backoff->retryUntil(10);

        $count = 0;
        $backoff->attempt($callback);

        self::assertSame(1, $count);



        $backoff = Backoff::noop()->maxAttempts(5)->retryExceptions();
        $backoff->retryUntil(10);
        $backoff->retryWhen(10);

        $count = 0;
        $backoff->attempt($callback);

        self::assertSame(5, $count);
    }





    /**
     * Test that different combinations of successCallbacks are called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_success_callback_is_called()
    {
        $maxAttempts = 5;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();

        $createCallback = fn(&$count)
            /** @var AttemptLog[] $logs */
            => function (array $logs) use (&$count) {
                $count++;
            };



        // one callback
        $count1 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);

        // two callbacks, separate params
        $count1 = $count2 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);

        // three callbacks, separate params, two in an array
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);

        // four callbacks, two calls, including an array
        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->successCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);
        self::assertSame(1, $count4);

        // three callbacks, separate calls
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->successCallback($createCallback($count1))
            ->successCallback($createCallback($count2))
            ->successCallback($createCallback($count3))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);

        // one callback as a callable array
        $invokableClass = new InvokableClass();
        $callable = [$invokableClass, '__invoke'];

        $newBackoff()
            ->successCallback($callable)
            ->attempt(fn() => true, null);
        self::assertSame(1, $invokableClass->getCount());
    }

    /**
     * Test that the success callback is called only once when expected, even if exceptions or invalid values have
     * caused multiple retries.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_success_callbacks_are_called_only_once_when_expected(): void
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
            foreach ([1, 2, 4, 5, 6] as $succeedAfter) {

                $count1 = 0;
                Backoff::noop()
                    ->retryExceptions()
                    ->maxAttempts($maxAttempts)
                    ->successCallback($createCallback($count1))
                    ->attempt($succeedAfterXCallback($succeedAfter), null);

                $expectedCount = ($maxAttempts > 0) && ($succeedAfter <= $maxAttempts) ? 1 : 0;
                self::assertSame($expectedCount, $count1); // <<<
            }
        }
    }

    /**
     * Test that the array of AttemptLogs are passed to success callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_attempt_logs_are_passed_to_success_callbacks(): void
    {
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

        foreach ([1, 2, 3, 4] as $succeedAfter) {

            $passedLogs = null;
            $callback = function (array $logs) use (&$passedLogs) {
                /** @var AttemptLog[] $logs */
                $passedLogs = $logs;
            };

            $backoff = Backoff::noop()
                ->maxAttempts(10)
                ->retryExceptions()
                ->successCallback($callback);
            $backoff->attempt($succeedAfterXCallback($succeedAfter), null);

            self::assertSame($backoff->logs(), $passedLogs);
        }
    }





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





    /**
     * Test that different combinations of finallyCallbacks are called.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_every_finally_callback_is_called()
    {
        $maxAttempts = 5;
        $newBackoff = fn() => Backoff::noop()->maxAttempts($maxAttempts)->retryExceptions();

        $createCallback = fn(&$count)
            /** @var AttemptLog[] $logs */
            => function (array $logs) use (&$count) {
                $count++;
            };



        // one callback
        $count1 = 0;
        $newBackoff()
            ->finallyCallback($createCallback($count1))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);

        // two callbacks, separate params
        $count1 = $count2 = 0;
        $newBackoff()
            ->finallyCallback($createCallback($count1), $createCallback($count2))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);

        // three callbacks, separate params, two in an array
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->finallyCallback($createCallback($count1), [$createCallback($count2), $createCallback($count3)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);

        // four callbacks, two calls, including an array
        $count1 = $count2 = $count3 = $count4 = 0;
        $newBackoff()
            ->finallyCallback($createCallback($count1))
            ->finallyCallback([$createCallback($count2), $createCallback($count3), $createCallback($count4)])
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);
        self::assertSame(1, $count4);

        // three callbacks, separate calls
        $count1 = $count2 = $count3 = 0;
        $newBackoff()
            ->finallyCallback($createCallback($count1))
            ->finallyCallback($createCallback($count2))
            ->finallyCallback($createCallback($count3))
            ->attempt(fn() => true, null);
        self::assertSame(1, $count1);
        self::assertSame(1, $count2);
        self::assertSame(1, $count3);

        // one callback as a callable array
        $invokableClass = new InvokableClass();
        $callable = [$invokableClass, '__invoke'];

        $newBackoff()
            ->finallyCallback($callable)
            ->attempt(fn() => true, null);
        self::assertSame(1, $invokableClass->getCount());
    }

    /**
     * Test that the finally callback is called only once when expected, even if exceptions or invalid values have
     * caused multiple retries.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_finally_callbacks_are_called_only_once_when_expected(): void
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
                    ->finallyCallback($createCallback($count1))
                    ->attempt($succeedAfterXCallback($succeedAfter), null);

                self::assertSame(1, $count1); // <<<

                // even when the exception is rethrown
                $count1 = 0;
                try {
                    Backoff::noop()
                        ->retryExceptions()
                        ->maxAttempts($maxAttempts)
                        ->finallyCallback($createCallback($count1))
                        ->attempt($succeedAfterXCallback($succeedAfter));
                } catch (Throwable) {
                }

                self::assertSame(1, $count1); // <<<
            }
        }
    }

    /**
     * Test that the array of AttemptLogs are passed to finally callbacks.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_attempt_logs_are_passed_to_finally_callbacks(): void
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
                ->finallyCallback($callback);
            $backoff->attempt(fn() => true, null);

            self::assertSame($backoff->logs(), $passedLogs);
        }
    }





    /**
     * Test that Backoff resets itself to its original state after calling ->attempt().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_returns_itself_to_its_original_state_after_running_attempts(): void
    {
        $backoff = Backoff::noop()->maxAttempts(5)->runsAtStartOfLoop()->retryExceptions();

        $backoff->attempt(fn() => throw new BackoffException(), null); // run attempt

        // now check how the loop acts
        $count = 0;
        while ($backoff->step()) {
            $count++;
        }
        $ranOk = ($count >= 4);
        $runsAtStartOfLoop = ($count == 5);

        self::assertTrue($ranOk);
        self::assertTrue($runsAtStartOfLoop);



        $backoff = Backoff::noop()->maxAttempts(5)->runsAtEndOfLoop()->retryExceptions();

        $backoff->attempt(fn() => throw new BackoffException(), null); // run attempt

        // now check how the loop acts
        $count = 0;
        while ($backoff->step()) {
            $count++;
        }
        $ranOk = ($count >= 4);
        $runsAtStartOfLoop = ($count == 5);

        self::assertTrue($ranOk);
        self::assertFalse($runsAtStartOfLoop); // runs after the first attempt this time
    }





    /**
     * Test that a Backoff instance can be reused.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_instance_can_be_reused(): void
    {
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
            throw new BackoffException();
        };

        $backoff = Backoff::noop()->maxAttempts(5)->retryExceptions();

        // use the backoff instance
        $count = 0;
        $backoff->attempt($callback, null);
        self::assertSame(5, $count);

        // reuse the backoff instance
        $count = 0;
        $backoff->attempt($callback, null);
        self::assertSame(5, $count);
    }





    /**
     * Test the methods that require the strategy to have not "started" yet.
     *
     * @test
     * @dataProvider methodsThatRequireTheStrategyToHaveNotStartedYetDataProvider
     *
     * @param callable $callBackoffMethod A function that returns a BackoffStrategy instance.
     * @return void
     */
    #[Test]
    #[DataProvider('methodsThatRequireTheStrategyToHaveNotStartedYetDataProvider')]
    public static function test_the_methods_that_require_the_strategy_not_to_have_started_yet(
        callable $callBackoffMethod
    ): void {

        $backoff = Backoff::noop();
        $backoff->calculate(); // causes the strategy to "start"

        $caughtException = false;
        try {
            $callBackoffMethod($backoff);
        } catch (BackoffRuntimeException) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }

    /**
     * DataProvider for test_the_methods_that_require_the_strategy_not_to_have_started_yet().
     *
     * @return array<callable[]>
     */
    public static function methodsThatRequireTheStrategyToHaveNotStartedYetDataProvider(): array
    {
        // "starting" basically means that the delayCalculator has been created with the desired settings

        return [
            [ fn(Backoff $backoff) => $backoff->fullJitter() ],
            [ fn(Backoff $backoff) => $backoff->equalJitter() ],
            [ fn(Backoff $backoff) => $backoff->jitterRange(1, 2) ],
            [ fn(Backoff $backoff) => $backoff->jitterCallback(fn() => 1) ],
            [ fn(Backoff $backoff) => $backoff->customJitter(new FullJitter()) ],
            [ fn(Backoff $backoff) => $backoff->noJitter() ],

            [ fn(Backoff $backoff) => $backoff->maxAttempts(1) ],
            [ fn(Backoff $backoff) => $backoff->noAttemptLimit() ],
            [ fn(Backoff $backoff) => $backoff->noMaxAttempts() ],

            [ fn(Backoff $backoff) => $backoff->maxDelay(1) ],
            [ fn(Backoff $backoff) => $backoff->noDelayLimit() ],
            [ fn(Backoff $backoff) => $backoff->noMaxDelay() ],

            [ fn(Backoff $backoff) => $backoff->unit(Settings::UNIT_SECONDS) ],
            [ fn(Backoff $backoff) => $backoff->unitSeconds() ],
            [ fn(Backoff $backoff) => $backoff->unitMs() ],
            [ fn(Backoff $backoff) => $backoff->unitUs() ],

            [ fn(Backoff $backoff) => $backoff->runsAtStartOfLoop() ],
            [ fn(Backoff $backoff) => $backoff->runsAtEndOfLoop() ],

            [ fn(Backoff $backoff) => $backoff->immediateFirstRetry() ],
            [ fn(Backoff $backoff) => $backoff->noImmediateFirstRetry() ],

            [ fn(Backoff $backoff) => $backoff->onlyDelayWhen(true) ],
            [ fn(Backoff $backoff) => $backoff->onlyRetryWhen(true) ],
        ];
    }





    /**
     * Test the AttemptLogs that are passed to callbacks
     *
     * retryExceptions(), exceptionCallback(), retryWhen(), retryUntil(), invalidResultCallback().
     *
     * @test
     * @dataProvider attemptLogDataProvider
     *
     * @param callable $defineAndRunBackoff Build and run the Backoff, using $callback in the way that's being tested.
     * @return void
     */
    #[Test]
    #[DataProvider('attemptLogDataProvider')]
    public static function test_the_attempt_logs_that_are_passed_to_callbacks(callable $defineAndRunBackoff): void
    {
        $startedAt = new DateTime();

        $attemptSequence = [];
        $maxAttempts = [];
        $prevDelaySequence = [];
        $nextDelaySequence = [];
        $workingTimeSequence = [];
        $overallDelaySequence = [];
        $overallWorkingTimeSequence = [];
        $unitTypeSequence = [];

        $createCallback = function (?bool $return = null) use (
            $startedAt,
            &$attemptSequence,
            &$maxAttempts,
            &$prevDelaySequence,
            &$nextDelaySequence,
            &$workingTimeSequence,
            &$overallDelaySequence,
            &$overallWorkingTimeSequence,
            &$unitTypeSequence,
        ) {

            return function (
                mixed $exceptionOrResult,
                AttemptLog $l,
            ) use (
                $startedAt,
                &$attemptSequence,
                &$maxAttempts,
                &$prevDelaySequence,
                &$nextDelaySequence,
                &$workingTimeSequence,
                &$overallDelaySequence,
                &$overallWorkingTimeSequence,
                &$unitTypeSequence,
                $return,
            ) {
                // testing $l…

                $attemptSequence[] = $l->attemptNumber();
                $maxAttempts[] = $l->maxAttempts();
                $unitTypeSequence[] = $l->unitType();



                // working-time
                $workingTimeSequence[] = $l->workingTime();
                is_null($l->workingTime())
                    ? self::assertNull($l->workingTimeInSeconds())
                    : self::assertEquals((float) ($l->workingTime() / 1000), $l->workingTimeInSeconds());
                is_null($l->workingTime())
                    ? self::assertNull($l->workingTimeInMs())
                    : self::assertSame($l->workingTime(), $l->workingTimeInMs());
                is_null($l->workingTime())
                    ? self::assertNull($l->workingTimeInUs())
                    : self::assertSame($l->workingTime() * 1000, $l->workingTimeInUs());

                // overall working-time
                $overallWorkingTimeSequence[] = $l->overallWorkingTime();
                is_null($l->overallWorkingTime())
                    ? self::assertNull($l->overallWorkingTimeInSeconds())
                    : self::assertEquals((float) ($l->overallWorkingTime() / 1000), $l->overallWorkingTimeInSeconds());
                is_null($l->overallWorkingTime())
                    ? self::assertNull($l->overallWorkingTimeInMs())
                    : self::assertSame($l->overallWorkingTime(), $l->overallWorkingTimeInMs());
                is_null($l->overallWorkingTime())
                    ? self::assertNull($l->overallWorkingTimeInUs())
                    : self::assertSame($l->overallWorkingTime() * 1000, $l->overallWorkingTimeInUs());



                // prev-delay
                $prevDelaySequence[] = $l->prevDelay();
                is_null($l->prevDelayInSeconds())
                    ? self::assertNull($l->prevDelayInSeconds())
                    : self::assertSame((float) ($l->prevDelay() / 1000), $l->prevDelayInSeconds());
                is_null($l->prevDelayInMs())
                    ? self::assertNull($l->prevDelayInMs())
                    : self::assertSame($l->prevDelay(), $l->prevDelayInMs());
                is_null($l->prevDelayInUs())
                    ? self::assertNull($l->prevDelayInUs())
                    : self::assertSame($l->prevDelay() * 1000, $l->prevDelayInUs());

                // next-delay
                $nextDelaySequence[] = $l->nextDelay();
                is_null($l->nextDelayInSeconds())
                    ? self::assertNull($l->nextDelayInSeconds())
                    : self::assertSame((float) ($l->nextDelay() / 1000), $l->nextDelayInSeconds());
                is_null($l->nextDelayInMs())
                    ? self::assertNull($l->nextDelayInMs())
                    : self::assertSame($l->nextDelay(), $l->nextDelayInMs());
                is_null($l->nextDelayInUs())
                    ? self::assertNull($l->nextDelayInUs())
                    : self::assertSame($l->nextDelay() * 1000, $l->nextDelayInUs());

                // overall delay
                $overallDelaySequence[] = $l->overallDelay();
                is_null($l->overallDelay())
                    ? self::assertNull($l->overallDelayInSeconds())
                    : self::assertSame((float) ($l->overallDelay() / 1000), $l->overallDelayInSeconds());
                is_null($l->overallDelay())
                    ? self::assertNull($l->overallDelayInMs())
                    : self::assertSame($l->overallDelay(), $l->overallDelayInMs());
                is_null($l->overallDelay())
                    ? self::assertNull($l->overallDelayInUs())
                    : self::assertSame($l->overallDelay() * 1000, $l->overallDelayInUs());



                // within 5 seconds
                self::assertLessThanOrEqual(
                    5,
                    abs($startedAt->getTimestamp() - $l->firstAttemptOccurredAt()->getTimestamp())
                );
                self::assertLessThanOrEqual(
                    5,
                    abs($startedAt->getTimestamp() - $l->thisAttemptOccurredAt()->getTimestamp())
                );

                return $return;
            };
        };



        $defineAndRunBackoff($createCallback);

        self::assertSame([1, 2, 3, 4, 5], $attemptSequence);
        self::assertSame([5, 5, 5, 5, 5], $maxAttempts);
        self::assertSame([null, 1, 2, 4, 8], $prevDelaySequence);
        self::assertSame([1, 2, 4, 8, null], $nextDelaySequence);
        self::assertSame([null, 1, 3, 7, 15], $overallDelaySequence);
        self::assertSame(
            ['milliseconds', 'milliseconds', 'milliseconds', 'milliseconds', 'milliseconds'],
            $unitTypeSequence
        );

        // we don't know how much time passed, so just check they're non-zero
        self::assertGreaterThan(0, array_sum($workingTimeSequence));
        self::assertGreaterThan(0, array_sum($overallWorkingTimeSequence));
    }

    /**
     * DataProvider for test_the_attempt_logs_that_are_passed_to_callbacks().
     *
     * @return array<string,array<string,callable>>
     */
    public static function attemptLogDataProvider(): array
    {
        return [

            // retryExceptions()
            'attempt logs for retryExceptions()' => [
                'defineAndRunBackoff' => fn(callable $createCallback) => Backoff::exponentialMs(1)
                    ->maxAttempts(5)
                    ->noJitter()
                    ->retryExceptions($createCallback(true))
                    ->attempt(function () {
                        usleep(1000);
                        throw new Exception();
                    }, null),
            ],

            // exceptionCallback()
            'attempt logs for exceptionCallback()' => [
                'defineAndRunBackoff' => fn(callable $createCallback) => Backoff::exponentialMs(1)
                    ->maxAttempts(5)
                    ->noJitter()
                    ->exceptionCallback($createCallback())
                    ->retryExceptions()
                    ->attempt(function () {
                        usleep(1000);
                        throw new Exception();
                    }, null),
            ],



            // retryWhen()
            'attempt logs for retryWhen()' => [
                'defineAndRunBackoff' => fn(callable $createCallback) => Backoff::exponentialMs(1)
                    ->maxAttempts(5)
                    ->noJitter()
                    ->retryWhen($createCallback(true))
                    ->attempt(function () {
                        usleep(1000);
                        return 10;
                    }, null),
            ],

            // retryUntil()
            'attempt logs for retryUntil()' => [
                'defineAndRunBackoff' => fn(callable $createCallback) => Backoff::exponentialMs(1)
                    ->maxAttempts(5)
                    ->noJitter()
                    ->retryUntil($createCallback(false))
                    ->attempt(function () {
                        usleep(1000);
                        return 10;
                    }, null),
            ],

            // invalidResultCallback()
            'attempt logs for invalidResultCallback()' => [
                'defineAndRunBackoff' => fn(callable $createCallback) => Backoff::exponentialMs(1)
                    ->maxAttempts(5)
                    ->noJitter()
                    ->invalidResultCallback($createCallback())
                    ->retryWhen(10)
                    ->attempt(function () {
                        usleep(1000);
                        return 10;
                    }, null),
            ],
        ];
    }
}
