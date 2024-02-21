<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\Exceptions\BackoffException;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\InvokableExceptionCallback;
use Exception;
use stdClass;
use Throwable;

/**
 * Test the Backoff class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffUnitTest extends PHPUnitTestCase
{
    /**
     * Test that Backoff catches exceptions and retries properly.
     *
     * @test
     * @dataProvider backoffRetryExceptionsDataProvider
     *
     * @param callable                                                       $attempt                The callback to
     *                                                                                               attempt.
     * @param array<class-string|callable|array<class-string|callable>>|null $retryExceptions1       The exceptions to
     *                                                                                               retry on (if
     *                                                                                               present).
     * @param array<class-string|callable|array<class-string|callable>>|null $retryExceptions2       The exceptions to
     *                                                                                               retry on (allowing
     *                                                                                               another call if
     *                                                                                               present).
     * @param integer                                                        $expectedAttempts       The number of
     *                                                                                               attempts expected.
     * @param mixed                                                          $expectedReturn         The expected
     *                                                                                               return value.
     * @param Throwable|null                                                 $expectedException      The expected
     *                                                                                               exception to be
     *                                                                                               caught.
     * @param boolean                                                        $expectRuntimeException Whether a
     *                                                                                               BackoffRuntimeExce-
     *                                                                                               ption is expected
     *                                                                                               (internal error).
     * @return void
     */
    public static function test_that_backoff_catches_exceptions_and_retries_properly(
        callable $attempt,
        ?array $retryExceptions1,
        ?array $retryExceptions2,
        int $expectedAttempts,
        mixed $expectedReturn,
        ?Throwable $expectedException,
        bool $expectRuntimeException,
    ): void {

        $count = 0;
        $wrappedAttempt = function () use (&$count, $attempt) {
            $count++;
            return $attempt();
        };

        // set up the backoff
        $strategy = BackoffStrategy::noop()->maxAttempts(5);
        $backoff = Backoff::new($strategy);

        if (is_array($retryExceptions1)) {
            $backoff->retryExceptions(...$retryExceptions1);
        }
        if (is_array($retryExceptions2)) {
            $backoff->retryExceptions(...$retryExceptions2);
        }

        // use the backoff to attempt the callback
        $return = null;
        $caughtException = null;
        $caughtRuntimeException = false;
        try {
            $return = $backoff->attempt($wrappedAttempt);
        } catch (BackoffRuntimeException) {
            $caughtRuntimeException = true;
        } catch (Throwable $e) {
            $caughtException = $e;
        }

        self::assertSame($expectedAttempts, $count);
        self::assertSame($expectedReturn, $return);
        if ($expectRuntimeException) {
            self::assertTrue($caughtRuntimeException);
        } else {
            self::assertSame($expectedException, $caughtException);
            self::assertFalse($caughtRuntimeException);
        }
    }

    /**
     * DataProvider for test_backoff_algorithm_output.
     *
     * @return array[]
     */
    public static function backoffRetryExceptionsDataProvider(): array
    {
        $randInt = mt_rand();
        $throwUntilAttempt = function ($throwUntil, $return, $throw) {
            return function () use (&$throwUntil, $return, $throw): int {
                return --$throwUntil <= 0
                    ? $return
                    : throw $throw;
            };
        };
        $backoffException = new BackoffException();
        $regularException = new Exception();
        $checkExceptionCallback1 = fn(Throwable $e) => $e instanceof BackoffException;
        $checkExceptionCallback2 = fn(int|Throwable $e) => $e instanceof BackoffException;
        $checkExceptionCallback3 = fn(Something|Other $e) => $e instanceof BackoffException;
        $checkExceptionCallback4 = fn(Throwable&Exception $e) => $e instanceof BackoffException;
        $checkExceptionCallback5 = fn(This&That $e) => $e instanceof BackoffException;

        return [

            // successful attempts

            'successful attempt - no catch' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt - catch all' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt - catch DiffException' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => [DiffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt - catch BackoffException' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => [BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt - catch via callback returning true' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt - catch via callback returning false' => [
                'attempt' => fn() => $randInt,
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],



            // unsuccessful attempts

            'unsuccessful attempts - no catch' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch all' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch via callback returning true' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch via callback returning false' => [
                'attempt' => fn() => throw $regularException,
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $regularException,
                'expectRuntimeException' => false,
            ],



            // successful attempts after 3 tries

            'successful attempt after 3 - no catch' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'retryExceptions1' => null,
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'successful attempt after 3 - catch all' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'retryExceptions1' => [],
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt after 3 - catch DiffException' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'retryExceptions1' => [DiffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'successful attempt after 3 - catch BackoffException' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'retryExceptions1' => [BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt after 3 - catch via callback returning true' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 3,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
                'expectRuntimeException' => false,
            ],
            'successful attempt after 3 - catch via callback returning false' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $regularException),
                'retryExceptions1' => [$checkExceptionCallback1],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $regularException,
                'expectRuntimeException' => false,
            ],



            // unsuccessful attempts
            // catch multiple things

            'unsuccessful attempts - catch DiffException and DiffException2' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class, DiffException2::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException and BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class, BackoffException::class],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],

            // catch defined with an array

            'unsuccessful attempts - catch DiffException, DiffException2 and DiffException2' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class, [DiffException2::class, DiffException3::class]],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException, DiffException2 and BackoffException' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class, [DiffException2::class, BackoffException::class]],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException, DiffException2 and callback returning false' => [
                'attempt' => fn() => throw $regularException,
                'retryExceptions1' => [DiffException::class, [DiffException2::class, $checkExceptionCallback1]],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $regularException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException, DiffException2 and callback returning true' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class, [DiffException2::class, $checkExceptionCallback1]],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],



            // test that calling multiple ->retryExceptions() multiple times adds to the list to check
            'unsuccessful attempts - catch DiffException, DiffException2, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class],
                'retryExceptions2' => [[DiffException2::class]],
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch DiffException, DiffException2 and BackoffException, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [DiffException::class],
                'retryExceptions2' => [[DiffException2::class, BackoffException::class]],
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch BackoffException, DiffException, defined diff times' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [BackoffException::class],
                'retryExceptions2' => [DiffException::class],
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],



            // callback with union types

            'unsuccessful attempts - catch via callback that has an union parameter including Exceptions' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [$checkExceptionCallback2],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch via callback that has an union parameter excluding Exceptions' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [$checkExceptionCallback3],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => null,
                'expectRuntimeException' => true,
            ],



            // callback with intersection types

            'unsuccessful attempts - catch via callback that has an intersection parameter including Exceptions' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [$checkExceptionCallback4],
                'retryExceptions2' => null,
                'expectedAttempts' => 5,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
                'expectRuntimeException' => false,
            ],
            'unsuccessful attempts - catch via callback that has an intersection parameter excluding Exceptions' => [
                'attempt' => fn() => throw $backoffException,
                'retryExceptions1' => [$checkExceptionCallback5],
                'retryExceptions2' => null,
                'expectedAttempts' => 1,
                'expectedReturn' => null,
                'expectToRethrow' => null,
                'expectRuntimeException' => true,
            ],
        ];
    }



    /**
     * Test that Backoff can retry based on exceptions when appropriate.
     *
     * @test
     * @dataProvider backoffRethrowDataProvider
     *
     * @param callable       $attempt                  The callback to attempt.
     * @param boolean|null   $rethrowLastException     Whether to rethrow the last exception.
     * @param boolean        $dontRethrowLastException Whether to not rethrow the last exception (called after).
     * @param mixed          $expectedReturn           The expected return value.
     * @param Throwable|null $expectedException        The expected exception to be caught.
     * @return void
     */
    public static function test_that_backoff_rethrows_the_last_exception(
        callable $attempt,
        ?bool $rethrowLastException,
        bool $dontRethrowLastException,
        mixed $expectedReturn,
        ?Throwable $expectedException,
    ): void {

        // set up the backoff
        $strategy = BackoffStrategy::noop()->maxAttempts(5);
        $backoff = Backoff::new($strategy);

        if (!is_null($rethrowLastException)) {
            $backoff->rethrowLastException($rethrowLastException);
        }
        if ($dontRethrowLastException) {
            $backoff->dontRethrowLastException();
        }

        // use the backoff to attempt the callback
        $return = null;
        $caughtException = null;
        try {
            $return = $backoff->attempt($attempt);
        } catch (Throwable $e) {
            $caughtException = $e;
        }

        self::assertSame($expectedReturn, $return);
        self::assertSame($expectedException, $caughtException);
    }

    /**
     * DataProvider for test_that_backoff_rethrows_the_last_exception.
     *
     * @return array[]
     */
    public static function backoffRethrowDataProvider(): array
    {
        $randInt = mt_rand();
        $backoffException = new BackoffException();

        return [

            // successful attempt

            'successful attempt - no rethrow' => [
                'attempt' => fn() => $randInt,
                'rethrowLastException' => null,
                'dontRethrowLastException' => false,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
            ],
            'successful attempt - rethrow false' => [
                'attempt' => fn() => $randInt,
                'rethrowLastException' => false,
                'dontRethrowLastException' => false,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
            ],
            'successful attempt - rethrow true' => [
                'attempt' => fn() => $randInt,
                'rethrowLastException' => true,
                'dontRethrowLastException' => false,
                'expectedReturn' => $randInt,
                'expectToRethrow' => null,
            ],



            // unsuccessful attempt

            'unsuccessful attempt - no rethrow' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => null,
                'dontRethrowLastException' => false,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
            ],
            'unsuccessful attempt - rethrow false' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => false,
                'dontRethrowLastException' => false,
                'expectedReturn' => null,
                'expectToRethrow' => null,
            ],
            'unsuccessful attempt - rethrow true' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => true,
                'dontRethrowLastException' => false,
                'expectedReturn' => null,
                'expectToRethrow' => $backoffException,
            ],



            // unsuccessful attempt + dontRethrowLastException()

            'unsuccessful attempt - no rethrow - plus don\'t rethrow' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => null,
                'dontRethrowLastException' => true,
                'expectedReturn' => null,
                'expectToRethrow' => null,
            ],
            'unsuccessful attempt - rethrow false - plus don\'t rethrow' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => false,
                'dontRethrowLastException' => true,
                'expectedReturn' => null,
                'expectToRethrow' => null,
            ],
            'unsuccessful attempt - rethrow true - plus don\'t rethrow' => [
                'attempt' => fn() => throw $backoffException,
                'rethrowLastException' => true,
                'dontRethrowLastException' => true,
                'expectedReturn' => null,
                'expectToRethrow' => null,
            ],
        ];
    }

    /**
     * Test that Backoff returns the default value.
     *
     * @test
     * @dataProvider backoffDefaultDataProvider
     *
     * @param callable $attempt        The callback to attempt.
     * @param mixed    $default        The default value to return.
     * @param mixed    $expectedReturn The expected return value.
     * @return void
     */
    public static function test_that_backoff_returns_the_default_value(
        callable $attempt,
        mixed $default,
        mixed $expectedReturn,
    ): void {

        $strategy = BackoffStrategy::noop()->maxAttempts(5);
        $backoff = Backoff::new($strategy)->rethrowLastException(false);

        $return = !is_null($default)
            ? $backoff->attempt($attempt, $default)
            : $backoff->attempt($attempt);

        self::assertSame($expectedReturn, $return);
    }

    /**
     * DataProvider for test_that_backoff_returns_the_default_value.
     *
     * @return array[]
     */
    public static function backoffDefaultDataProvider(): array
    {
        $randBool = mt_rand(0, 1) ? true : false;
        $randInt = mt_rand();
        $randFloat = mt_rand(1, 100) / mt_rand(1, 100);
        $randString = md5(mt_rand());
        $randArray = [mt_rand()];
        $randStdClass = new stdClass();
        $randStdClass->abc = mt_rand();
        $throwUntilAttempt = function ($throwUntil, $return, $throw) {
            return function () use (&$throwUntil, $return, $throw): int {
                return --$throwUntil <= 0
                    ? $return
                    : throw $throw;
            };
        };
        $backoffException = new BackoffException();

        return [
            'throw until maxAttempts - no default' => [
                'attempt' => fn() => throw $backoffException,
                'default' => null,
                'expectedReturn' => null,
            ],

            'throw until maxAttempts - default bool' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randBool,
                'expectedReturn' => $randBool,
            ],
            'throw until attempt 3 - default bool' => [
                'attempt' => $throwUntilAttempt(3, $randInt, $backoffException),
                'default' => $randBool,
                'expectedReturn' => $randBool,
            ],

            'throw until maxAttempts - default int' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randInt,
                'expectedReturn' => $randInt,
            ],

            'throw until maxAttempts - default float' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randFloat,
                'expectedReturn' => $randFloat,
            ],

            'throw until maxAttempts - default string' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randString,
                'expectedReturn' => $randString,
            ],

            'throw until maxAttempts - default array' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randArray,
                'expectedReturn' => $randArray,
            ],

            'throw until maxAttempts - default stdClass' => [
                'attempt' => fn() => throw $backoffException,
                'default' => $randStdClass,
                'expectedReturn' => $randStdClass,
            ],
        ];
    }

    /**
     * Test that Backoff calls the exception callbacks, correctly based upon their parameter types.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_backoff_calls_exception_callbacks(): void
    {
        $strategy = BackoffStrategy::noop()->maxAttempts(2);

        $count1 = 0;
        $callback1 = function (Throwable $x) use (&$count1) {
            $count1++;
        };

        // has a union type
        $count2 = 0;
        $callback2 = function (int|Throwable $e) use (&$count2) {
            $count2++;
        };

        $count3 = 0;
        $callback3 = function (Throwable $e) use (&$count3) {
            $count3++;
        };

        // has no parameters
        $count4 = 0;
        $callback4 = function () use (&$count4) {
            $count4++;
        };

        // expects a BackoffException
        $count5 = 0;
        $callback5 = function (BackoffException $e) use (&$count5) {
            $count5++;
        };

        // expects a BackoffInitialisationException (child of BackoffException)
        $count6 = 0;
        $callback6 = function (BackoffInitialisationException $e) use (&$count6) {
            $count6++;
        };

        // will throw an error because its parameter is not a Throwable
        $callback7 = fn(SomeNonException $e) => true;

        // will throw an error because it expects more than one parameter
        $callback8 = fn(Throwable $e, Throwable $e2) => true;

        // will throw an error because it expects a non-exception parameter
        $callback9 = fn($e) => true;

        // will throw an error because it expects a non-exception parameter
        $callback10 = fn(int $e) => true;

        // an invokable class
        $invokableClass = new InvokableExceptionCallback();

        // a callable
        $callable = [$invokableClass, '__invoke'];

        // has a parameter with a uninon type hint excluding Exceptions - todo - how to test this?
        $callback11 = fn(Something|Other $e) => true;

        // has a parameter with an intersection type hint including Exceptions - todo - how to test this?
        $count12 = 0;
        $callback12 = function (Throwable&Exception $e) use (&$count12) {
            $count12++;
        };

        // has a parameter with an intersection type hint excluding Exceptions - todo - how to test this?
        $callback13 = fn(This&That $e) => true;



        // callUponException called once
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);



        // callUponException called with an array
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException([$callback1, $callback2]); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count2);



        // callUponException called with an array, and single callback
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException([$callback1, $callback2], $callback3); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count2);
        self::assertSame(2, $count3);



        // callUponException called with an array and another array
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException([$callback1], [$callback2, $callback3]); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count2);
        self::assertSame(2, $count3);



        // callUponException called several times
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1) // <<<
            ->callUponException([$callback2, $callback3]); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count2);
        self::assertSame(2, $count3);



        // a callback passed that has no parameters
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback4); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count4);



        // a callback passed that expects a BackoffException to be passed
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback5); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(2, $count5);



        // a callback passed that expects a BackoffInitialisationException to be passed
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback6); // <<<

        $backoff->attempt(fn() => throw new BackoffException());

        self::assertSame(2, $count1);
        self::assertSame(0, $count6);



        // a callback passed that expects more than 1 parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback8); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback7); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback8); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback9); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback10); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $invokableClass); // <<<

        $backoff->attempt(fn() => throw new BackoffException());



        // a callback passed that expects a non-exception parameter
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callable); // <<<

        $backoff->attempt(fn() => throw new BackoffException());



        // a callback passed with an intersection type hint including Exceptions
        $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->rethrowLastException(false)
            ->callUponException($callback1, $callback11); // <<<

        $caughtException = false;
        try {
            $backoff->attempt(fn() => throw new BackoffException());
        } catch (Throwable) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);



        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {

            // a callback passed with an intersection type hint including Exceptions
            $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
            $backoff = Backoff::new($strategy)
                ->retryExceptions()
                ->rethrowLastException(false)
                ->callUponException($callback1, $callback12); // <<<

            $backoff->attempt(fn() => throw new BackoffException());

            self::assertSame(2, $count1);
            self::assertSame(2, $count12);
        }



        if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
            // a callback passed with an intersection type hint excluding Exceptions
            $count1 = $count2 = $count3 = $count4 = $count5 = $count6 = $count12 = 0;
            $backoff = Backoff::new($strategy)
                ->retryExceptions()
                ->rethrowLastException(false)
                ->callUponException($callback1, $callback13); // <<<

            $caughtException = false;
            try {
                $backoff->attempt(fn() => throw new BackoffException());
            } catch (Throwable) {
                $caughtException = true;
            }
            self::assertTrue($caughtException);
        }
    }

    /**
     * Test that a Backoff instance can be reused.
     *
     * @return void
     */
    public static function test_that_backoff_instance_can_be_reused(): void
    {
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
            throw new BackoffException();
        };

        $strategy = BackoffStrategy::noop()->maxAttempts(5);
        $backoff = Backoff::new($strategy)
            ->retryExceptions()
            ->dontRethrowLastException();

        // use the backoff instance
        $count = 0;
        $backoff->attempt($callback);
        self::assertSame(5, $count);

        // reuse the backoff instance
        $count = 0;
        $backoff->attempt($callback);
        self::assertSame(5, $count);
    }
}
