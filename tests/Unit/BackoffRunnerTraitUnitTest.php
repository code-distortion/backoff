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
use DateTime;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Throwable;

/**
 * Test the BackoffRunnerTrait - test the general backoff-runner functionality.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitUnitTest extends PHPUnitTestCase
{
    /**
     * Test what Backoff returns - including when exceptions occur and invalid results are returned, and when default
     * values are specified in different places (exception, return and attempt default values).
     *
     * @test
     * @dataProvider backoffRethrowDataProvider
     *
     * @param callable       $attempt               The callback to attempt.
     * @param boolean        $checkForExceptions    The exception to retry on (if present).
     * @param boolean        $useExceptionDefault   Whether to use an "exception" default value or not.
     * @param mixed          $exceptionDefault      The default "exception" value to use.
     * @param boolean        $checkForInvalidValues Whether to check for invalid return values or not.
     * @param mixed          $retryWhenVal          Retry when the result is this.
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
        bool $checkForExceptions,
        bool $useExceptionDefault,
        mixed $exceptionDefault,
        bool $checkForInvalidValues,
        mixed $retryWhenVal,
        bool $useReturnDefault,
        mixed $returnDefault,
        bool $useAttemptDefault,
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
                ? $backoff->retryWhen($retryWhenVal, true, $returnDefault)
                : $backoff->retryWhen($retryWhenVal, true);
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
     * @return array<string,array<string,boolean|integer|float|string|array<integer>|stdClass|callable|\Closure|BackoffException|null>>
     * @throws Exception Doesn't actually throw this, however phpcs expects it.
     */
    public static function backoffRethrowDataProvider(): array
    {
        $default = [
            'attempt' => fn() => null,
            'checkForExceptions' => false,
            'useExceptionDefault' => false,
            'exceptionDefault' => null,
            'checkForInvalidValues' => false,
            'retryWhenVal' => null,
            'useReturnDefault' => false,
            'returnDefault' => null,
            'useAttemptDefault' => false,
            'attemptDefault' => null,
            'expectedResult' => null,
            'expectedException' => null,
        ];

        $return = [];
        foreach (['bool', 'int', 'float', 'string', 'array', 'stdClass', 'callable'] as $type) {

            $successVal = self::generateRandomValueOfType($type);
            $exceptionDefault = self::generateRandomValueOfType($type);
            $returnDefault = self::generateRandomValueOfType($type);
            $attemptDefault = self::generateRandomValueOfType($type);

            do {
                $notSuccessVal = mt_rand();
            } while ($notSuccessVal === $successVal);

            $successValResolved = $successVal; // doesn't need resolving; will be returned directly, even when callable
            $exceptionDefaultResolved = is_callable($exceptionDefault)
                ? $exceptionDefault()
                : $exceptionDefault;
            $returnDefaultResolved = is_callable($returnDefault)
                ? $returnDefault()
                : $returnDefault;
            $attemptDefaultResolved = is_callable($attemptDefault)
                ? $attemptDefault()
                : $attemptDefault;

            $backoffException = new BackoffException();

            $nextReturn = [

                // successful attempt

                // successful attempt: 0
                "successful attempt $type" => [
                    'attempt' => fn() => $successVal,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 1
                "successful attempt $type: (check for exp)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp + exp default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 2
                "successful attempt $type: (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 3
                "successful attempt $type: (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 1 + 2
                "successful attempt $type: (check for exp) (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 1 + 3
                "successful attempt $type: (check for exp) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp + exp default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 2 + 3
                "successful attempt $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],

                // successful attempt: 1 + 2 + 3
                "successful attempt $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: "
                . "(check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
                ],
                "successful attempt $type: "
                . "(check for exp + exp default) (chk result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => $successVal,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $successValResolved,
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
                    'exceptionDefault' => $exceptionDefault,
                    'expectedResult' => $exceptionDefaultResolved,
                ],

                // throws exception: 2
                "throws exception $type: (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedException' => $backoffException,
                ],

                // throws exception: 3
                "throws exception $type: (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],

                // throws exception: 1 + 2
                "throws exception $type: (check for exp) (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'expectedResult' => $exceptionDefaultResolved,
                ],
                "throws exception $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedException' => $backoffException,
                ],
                "throws exception $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $exceptionDefaultResolved,
                ],

                // throws exception: 1 + 3
                "throws exception $type: (check for exp) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "throws exception $type: (check for exp + exp default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $exceptionDefaultResolved,
                ],

                // throws exception: 2 + 3
                "throws exception $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "throws exception $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],

                // throws exception: 1 + 2 + 3
                "throws exception $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "throws exception $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $exceptionDefaultResolved,
                ],
                "throws exception $type: (check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "throws exception $type: "
                . "(check for exp + exp default) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => throw $backoffException,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => $notSuccessVal,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $exceptionDefaultResolved,
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
//                    'exceptionDefault' => $exceptionDefault,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 2
                "invalid result $type: (check result)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],

                // invalid result: 3
//                "invalid result $type: (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptDefault,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 1 + 2
                "invalid result $type: (check for exp) (check result)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check for exp + exp default) (check result)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'expectedResult' => false, // <<< the invalid result is returned at the end
                ],
                "invalid result $type: (check for exp) (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],
                "invalid result $type: (check for exp + exp default) (check result + \"return\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],

                // invalid result: 1 + 3
//                "invalid result $type: (check for exp) (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => false,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptDefault,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],
//                "invalid result $type: (check for exp + exp default) (\"attempt\" default)" => [
//                    'attempt' => fn() => false,
//                    'checkForExceptions' => true,
//                    'useExceptionDefault' => true,
//                    'exceptionDefault' => $exceptionDefault,
//                    'useAttemptDefault' => true,
//                    'attemptDefault' => $attemptDefault,
//                    'expectedResult' => false, // <<< the value isn't deemed invalid because it's not checked for
//                ],

                // invalid result: 2 + 3
                "invalid result $type: (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "invalid result $type: (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],

                // invalid result: 1 + 2 + 3
                "invalid result $type: (check for exp) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "invalid result $type: (check for exp + exp default) (check result) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $attemptDefaultResolved,
                ],
                "invalid result $type: (check for exp) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => false,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],
                "invalid result $type: "
                . "(check for exp + exp default) (check result + \"return\" default) (\"attempt\" default)" => [
                    'attempt' => fn() => false,
                    'checkForExceptions' => true,
                    'useExceptionDefault' => true,
                    'exceptionDefault' => $exceptionDefault,
                    'checkForInvalidValues' => true,
                    'retryWhenVal' => false,
                    'useReturnDefault' => true,
                    'returnDefault' => $returnDefault,
                    'useAttemptDefault' => true,
                    'attemptDefault' => $attemptDefault,
                    'expectedResult' => $returnDefaultResolved,
                ],
            ];

            $return = array_merge($return, $nextReturn);
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
        $runsAtStartOfLoop = ($count === 5);

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
        $runsAtStartOfLoop = ($count === 5);

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
        $backoff->step(false); // causes the strategy to "start"

        $caughtException = false;
        try {
            $callBackoffMethod($backoff);
        } catch (BackoffRuntimeException) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }

    /**
     * DataProvider for test_the_methods_that_require_the_strategy_not_to_have_started_yet.
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
            [ fn(Backoff $backoff) => $backoff->noMaxAttempts() ],
            [ fn(Backoff $backoff) => $backoff->noAttemptLimit() ],

            [ fn(Backoff $backoff) => $backoff->maxDelay(1) ],
            [ fn(Backoff $backoff) => $backoff->noMaxDelay() ],
            [ fn(Backoff $backoff) => $backoff->noDelayLimit() ],

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
     * Test the AttemptLogs that are passed to callbacks.
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
        ): callable {

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
            ): bool|null {
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
                    : self::assertSame((float) $l->prevDelay() / 1000, $l->prevDelayInSeconds());
                is_null($l->prevDelayInMs())
                    ? self::assertNull($l->prevDelayInMs())
                    : self::assertSame($l->prevDelay(), $l->prevDelayInMs());
                is_null($l->prevDelayInUs())
                    ? self::assertNull($l->prevDelayInUs())
                    : self::assertSame((int) $l->prevDelay() * 1000, $l->prevDelayInUs());

                // next-delay
                $nextDelaySequence[] = $l->nextDelay();
                is_null($l->nextDelayInSeconds())
                    ? self::assertNull($l->nextDelayInSeconds())
                    : self::assertSame((float) $l->nextDelay() / 1000, $l->nextDelayInSeconds());
                is_null($l->nextDelayInMs())
                    ? self::assertNull($l->nextDelayInMs())
                    : self::assertSame($l->nextDelay(), $l->nextDelayInMs());
                is_null($l->nextDelayInUs())
                    ? self::assertNull($l->nextDelayInUs())
                    : self::assertSame((int) $l->nextDelay() * 1000, $l->nextDelayInUs());

                // overall delay
                $overallDelaySequence[] = $l->overallDelay();
                is_null($l->overallDelay())
                    ? self::assertNull($l->overallDelayInSeconds())
                    : self::assertSame((float) $l->overallDelay() / 1000, $l->overallDelayInSeconds());
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
     * DataProvider for test_the_attempt_logs_that_are_passed_to_callbacks.
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
