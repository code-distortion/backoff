<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn1;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn2;
use CodeDistortion\Backoff\Tests\Unit\Support\OtherExcptn3;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * Test the BackoffRunnerTrait - test retrying because of exceptions.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitRetryBecauseOfExceptionsUnitTest extends PHPUnitTestCase
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
        $throwUntilAttempt = function (int $throwUntil, mixed $return, Throwable $throw) {
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
     * Test that exception defaults take priority over the default passed to ->attempt().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_exception_defaults_take_priority_over_attempt_default(): void
    {
        $default1 = mt_rand();
        $default2 = mt_rand();

        $result = Backoff::noop()
            ->maxAttempts(1)
            ->retryAllExceptions($default1)
            ->attempt(fn() => throw new Exception(), $default2);

        self::assertSame($default1, $result);
    }

    /**
     * Test the catching of exceptions when calling ->retryAllExceptions() and ->retryExceptions() in different orders,
     * and with different default values.
     *
     * @test
     * @dataProvider retryAllExceptionsBeforeOrAfterRetryExceptionsDataProvider
     *
     * @param boolean        $callRetryExceptionsFirst  Whether to call retryExceptions() first.
     * @param boolean|null   $callRetryAllExceptions    Whether to call retryAllExceptions().
     * @param boolean        $callRetryExceptionsSecond Whether to call retryExceptions() after retryAllExceptions().
     * @param string|null    $attemptDefault            The default value to return when the max attempts are reached.
     * @param string|null    $allExceptionsDefault      The default value to return when any exception is caught.
     * @param string|null    $anExceptionDefault        The default value to return when a specific exception is caught.
     * @param Throwable      $exceptionToThrow          The exception to throw.
     * @param boolean        $expectedToRetry           Whether the exception should be retried.
     * @param string|null    $expectedReturn            The value that should be returned.
     * @param Throwable|null $expectException           Whether an exception is expected to be caught.
     * @return void
     */
    #[Test]
    #[DataProvider('retryAllExceptionsBeforeOrAfterRetryExceptionsDataProvider')]
    public static function test_when_retry_all_exceptions_is_called_before_or_after_retry_exceptions(
        bool $callRetryExceptionsFirst,
        ?bool $callRetryAllExceptions,
        bool $callRetryExceptionsSecond,
        ?string $attemptDefault,
        ?string $allExceptionsDefault,
        ?string $anExceptionDefault,
        Throwable $exceptionToThrow,
        bool $expectedToRetry,
        ?string $expectedReturn,
        ?Throwable $expectException,
    ): void {

        $attemptCount = 0;
        $callback = function (Throwable $exception) use (&$attemptCount) {
            $attemptCount = 0;
            return function () use (&$attemptCount, $exception) {
                $attemptCount++;
                throw $exception;
            };
        };



        // set up the Backoff
        $backoff = Backoff::noop()->maxAttempts(2);

        if ($callRetryExceptionsFirst) {
            !is_null($anExceptionDefault)
                ? $backoff->retryExceptions(OtherExcptn1::class, $anExceptionDefault)
                : $backoff->retryExceptions(OtherExcptn1::class);
        }

        if ($callRetryAllExceptions === true) {
            !is_null($allExceptionsDefault)
                ? $backoff->retryAllExceptions($allExceptionsDefault)
                : $backoff->retryAllExceptions();
        } elseif ($callRetryAllExceptions === false) {
            !is_null($allExceptionsDefault)
                ? $backoff->dontRetryExceptions($allExceptionsDefault)
                : $backoff->dontRetryExceptions();
        }

        if ($callRetryExceptionsSecond) {
            !is_null($anExceptionDefault)
                ? $backoff->retryExceptions(OtherExcptn1::class, $anExceptionDefault)
                : $backoff->retryExceptions(OtherExcptn1::class);
        }



        // run the backoff
        $caughtException = null;
        $return = null;
        try {
            $return = !is_null($attemptDefault)
                ? $backoff->attempt($callback($exceptionToThrow), $attemptDefault)
                : $backoff->attempt($callback($exceptionToThrow));
        } catch (Throwable $caughtException) {
        }



        // check what happened
        self::assertSame($expectException, $caughtException);
        self::assertSame($expectedToRetry ? 2 : 1, $attemptCount);
        if (is_null($expectException)) {
            self::assertSame($expectedReturn, $return);
        }
    }

    /**
     * DataProvider for test_when_retry_all_exceptions_is_called_before_or_after_retry_exceptions.
     *
     * @return array<integer,array<string,boolean|Exception|string|null>>
     */
    public static function retryAllExceptionsBeforeOrAfterRetryExceptionsDataProvider(): array
    {
        $attemptDefault = 'attempt default';
        $allExceptionsDefault = 'all exceptions default';
        $anExceptionDefault = 'an exception default';

        $exception = new Exception();
        $otherException1 = new OtherExcptn1();

        $return = [];

        foreach ([0, 1, 2] as $callRetryExceptionsWhen) {
            foreach ([null, true, false] as $callRetryAllExceptions) {
                foreach ([null, $attemptDefault] as $currentAttemptDefault) {
                    foreach ([null, $allExceptionsDefault] as $currentAllExceptionsDefault) {
                        foreach ([null, $anExceptionDefault] as $currentAnExceptionDefault) {
                            foreach ([$exception, $otherException1] as $exceptionToThrow) {

                                // skip some inconsequential combinations
                                if (($callRetryExceptionsWhen === 0) && (!is_null($currentAnExceptionDefault))) {
                                    continue;
                                }
                                if ((is_null($callRetryAllExceptions)) && (!is_null($currentAllExceptionsDefault))) {
                                    continue;
                                }

                                $expectedToRetry = true; // default: catch all exceptions
                                $expectedReturn = $currentAttemptDefault;

                                // when catching a particular exception (BEFORE possibly calling retryAllExceptions())
                                $caughtAnException = false;
                                if ($callRetryExceptionsWhen === 1) {
                                    if ($exceptionToThrow === $otherException1) {
                                        $expectedToRetry = true;
                                        $caughtAnException = true;
                                        $expectedReturn = $currentAnExceptionDefault ?? $currentAttemptDefault;
                                    } else {
                                        $expectedToRetry = false;
                                        $expectedReturn = $currentAttemptDefault;
                                    }
                                }

                                // when catching all exceptions ->retryAllExceptions()
                                // or not catching any exceptions ->dontRetryExceptions()
                                if ($callRetryAllExceptions === true) {
                                    $expectedToRetry = true;
                                    // allow the an-exception default to be included, it takes priority
                                    $expectedReturn = $caughtAnException
                                        ? ($currentAnExceptionDefault
                                            ?? $currentAllExceptionsDefault
                                            ?? $currentAttemptDefault
                                        )
                                        : ($currentAllExceptionsDefault ?? $currentAttemptDefault);
                                } elseif ($callRetryAllExceptions === false) {
                                    $expectedToRetry = false;
                                    // a default is allowed when not catching all exceptions
                                    $expectedReturn = $currentAllExceptionsDefault ?? $currentAttemptDefault;
                                }

                                // when catching a particular exception (AFTER possibly calling retryAllExceptions())
                                if ($callRetryExceptionsWhen === 2) {

                                    // if neither ->retryAllExceptions() or ->dontRetryExceptions() were called
                                    // the default action (of retrying all exceptions) is ignored now that
                                    // ->retryExceptions() is called
                                    if (is_null($callRetryAllExceptions)) {
                                        $expectedToRetry = false;
                                    }

                                    // if ->dontRetryExceptions() was called, then it's default
                                    // is removed now that ->retryExceptions() is called
                                    if ($callRetryAllExceptions === false) {
                                        $expectedReturn = null;
                                    }

                                    if ($exceptionToThrow === $otherException1) {
                                        $expectedToRetry = true;
                                        // allow the all-exceptions default to be included
                                        $expectedReturn = ($callRetryAllExceptions === true)
                                            ? ($currentAnExceptionDefault
                                                ?? $currentAllExceptionsDefault
                                                ?? $currentAttemptDefault
                                            )
                                            : ($currentAnExceptionDefault ?? $currentAttemptDefault);
                                    }
                                }

                                // add the attempt default, which happens when it wasn't set when catching exceptions
                                $expectedReturn ??= $currentAttemptDefault;

                                $expectException = is_null($expectedReturn)
                                    ? $exceptionToThrow
                                    : null;

                                $return[] = [
                                    'callRetryExceptionsFirst' => $callRetryExceptionsWhen === 1,
                                    'callRetryAllExceptions' => $callRetryAllExceptions,
                                    'callRetryExceptionsSecond' => $callRetryExceptionsWhen === 2,
                                    'attemptDefault' => $currentAttemptDefault,
                                    'allExceptionsDefault' => $currentAllExceptionsDefault,
                                    'anExceptionDefault' => $currentAnExceptionDefault,
                                    'exceptionToThrow' => $exceptionToThrow,
                                    'expectedToRetry' => $expectedToRetry,
                                    'expectedReturn' => $expectedReturn,
                                    'expectException' => $expectException,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $return;
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

        $createCallback = fn(int &$count) => function (Throwable $e, AttemptLog $log, bool $willRetry) use (&$count) {
            $count++;
        };

        // WILL retry exceptions - will call the callback more than once
        $count = 0;
        $newBackoff()
            ->exceptionCallback($createCallback($count))
            ->attempt(fn() => throw new Exception(), null);
        self::assertSame(5, $count); // <<<

        // will NOT retry exceptions - will call the callback once
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
     * Test that the "don't retry exceptions" default takes priority over the default passed to ->attempt().
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_dont_retry_exceptions_default_takes_priority_over_attempt_default(): void
    {
        $default1 = mt_rand();
        $default2 = mt_rand();

        $result = Backoff::noop()
            ->maxAttempts(1)
            ->dontRetryExceptions($default1)
            ->attempt(fn() => throw new Exception(), $default2);

        self::assertSame($default1, $result);
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
}
