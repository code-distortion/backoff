<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the BackoffRunnerTrait - test "retry when" and "retry until" checks.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffRunnerTraitRetryBecauseOfInvalidValuesUnitTest extends PHPUnitTestCase
{
    /**
     * Test that Backoff checks for invalid "retry when" values, and retries because of them.
     *
     * @test
     * @dataProvider backoffRetryWhenResponseDataProvider
     *
     * @param callable   $attempt              The callback to attempt.
     * @param mixed|null $invalidValues1       The values to retry on (if present).
     * @param boolean    $invalidValues1Strict Whether the 1st values should be checked strictly.
     * @param mixed|null $invalidValues2       The values to retry on (allowing another call if present).
     * @param boolean    $invalidValues2Strict Whether the 2nd values should be checked strictly.
     * @param boolean    $expectedToRetry      Whether the exception should be retried.
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
        bool $expectedToRetry,
        mixed $expectedResult,
    ): void {

        $attemptCount = 0;
        $wrappedAttempt = function () use (&$attemptCount, $attempt) {
            $attemptCount++;
            return $attempt();
        };

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(2);

        if (!is_null($invalidValues1)) {
            $backoff->retryWhen($invalidValues1, $invalidValues1Strict);
        }
        if (!is_null($invalidValues2)) {
            $backoff->retryWhen($invalidValues2, $invalidValues2Strict);
        }

        // use the backoff to attempt the callback
        $result = $backoff->attempt($wrappedAttempt);

        self::assertSame($expectedToRetry ? 2 : 1, $attemptCount);
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
            'expectedToRetry' => false,
            'expectedResult' => $randInt1,
        ];

        $return = [

            // successful attempts

            'successful attempt' => [],

            // not strict
            'successful attempt - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'expectedToRetry' => false,
            ],
            'successful attempt - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues2' => $randInt1 - 1,
                'expectedToRetry' => false,
            ],
            'successful attempt - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1,
                'expectedToRetry' => true,
            ],
            'successful attempt - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues2' => $randInt1,
                'expectedToRetry' => true,
            ],

            // strict
            'successful attempt (strict) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'invalidValues2' => $randInt1 - 1,
                'invalidValues2Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1,
                'invalidValues1Strict' => true,
                'expectedToRetry' => true,
            ],
            'successful attempt (strict) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1 + 1,
                'invalidValues1Strict' => true,
                'invalidValues2' => $randInt1,
                'invalidValues2Strict' => true,
                'expectedToRetry' => true,
            ],

            // strict 2
            'successful attempt (strict 2) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict 2) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'invalidValues2' => (float) $randInt1,
                'invalidValues2Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict 2) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => $randInt1,
                'invalidValues1Strict' => true,
                'expectedToRetry' => true,
            ],
            'successful attempt (strict 2) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => (bool) $randInt1,
                'invalidValues1Strict' => true,
                'invalidValues2' => $randInt1,
                'invalidValues2Strict' => true,
                'expectedToRetry' => true,
            ],

            // callback
            'successful attempt (callback) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => false,
                'expectedToRetry' => false,
            ],
            'successful attempt (callback) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => false,
                'invalidValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedToRetry' => false,
            ],
            'successful attempt (callback) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => true,
                'expectedToRetry' => true,
            ],
            'successful attempt (callback) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'invalidValues1' => fn($value) => true,
                'invalidValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedToRetry' => true,
            ],
        ];

        foreach ($return as $index => $value) {
            $return[$index] = array_merge($default, $value);
        }

        return $return;
    }



    /**
     * Test that Backoff applies the default value properly when using ->retryWhen().
     *
     * @test
     * @dataProvider backoffRetryWhenDefaultDataProvider
     *
     * @param string               $returnValue      The value to return.
     * @param string|callable      $retryWhenValue   The value to retry on.
     * @param string|callable|null $retryWhenDefault The default value to use.
     * @param string|callable|null $attemptDefault   The default value to use.
     * @param string               $expectedResult   The expected return value.
     * @return void
     */
    #[Test]
    #[DataProvider('backoffRetryWhenDefaultDataProvider')]
    public static function test_retry_when_default_values(
        string $returnValue,
        string|callable $retryWhenValue,
        string|callable|null $retryWhenDefault,
        string|callable|null $attemptDefault,
        string $expectedResult,
    ): void {

        $backoff = Backoff::noop()->maxAttempts(2);
        !is_null($retryWhenDefault)
            ? $backoff->retryWhen($retryWhenValue, true, $retryWhenDefault)
            : $backoff->retryWhen($retryWhenValue);

        $result = !is_null($attemptDefault)
            ? $backoff->attempt(fn() => $returnValue, $attemptDefault)
            : $backoff->attempt(fn() => $returnValue);

        self::assertSame($expectedResult, $result);
    }

    /**
     * DataProvider for test_retry_when_default_values.
     *
     * @return array<array<string,string|callable|null>>
     */
    public static function backoffRetryWhenDefaultDataProvider(): array
    {
        $return = [];

        $callableTrue = fn() => true;
        $callableFalse = fn() => false;

        $retryWhenDefault = 'retry-when-default';
        $retryWhenDefaultCallable = fn() => $retryWhenDefault;

        $attemptDefault = 'attempt-default';
        $attemptDefaultCallable = fn() => $attemptDefault;

        $returnValue = 'return-value';

        foreach ([$returnValue, 'no-match', $callableTrue, $callableFalse] as $currentRetryWhenValue) {
            foreach ([null, $retryWhenDefault, $retryWhenDefaultCallable] as $currentRetryWhenDefault) {
                foreach ([null, $attemptDefault, $attemptDefaultCallable] as $currentAttemptDefault) {

                    $matches = match ($currentRetryWhenValue) {
                        $returnValue => true,
                        $callableTrue => true,
                        default => false,
                    };

                    $expectedResult = $matches
                        ? ($currentRetryWhenDefault ?? $currentAttemptDefault)
                        : null;

                    if (is_callable($expectedResult)) {
                        $expectedResult = $expectedResult();
                    } elseif (is_null($expectedResult)) {
                        $expectedResult = $returnValue;
                    }

                    $return[] = [
                        'returnValue' => $returnValue,
                        'retryWhenValue' => $currentRetryWhenValue,
                        'retryWhenDefault' => $currentRetryWhenDefault,
                        'attemptDefault' => $currentAttemptDefault,
                        'expectedResult' => $expectedResult,
                    ];
                }
            }
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
     * @param mixed|null $validValues1       The values to retry until (if present).
     * @param boolean    $validValues1Strict Whether the 1st values should be checked strictly.
     * @param mixed|null $validValues2       The values to retry until (allowing another call if present).
     * @param boolean    $validValues2Strict Whether the 2nd values should be checked strictly.
     * @param boolean    $expectedToRetry    Whether the exception should be retried.
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
        bool $expectedToRetry,
        mixed $expectedResult,
    ): void {

        $attemptCount = 0;
        $wrappedAttempt = function () use (&$attemptCount, $attempt) {
            $attemptCount++;
            return $attempt();
        };

        // set up the backoff
        $backoff = Backoff::noop()->maxAttempts(2);

        if (!is_null($validValues1)) {
            $backoff->retryUntil($validValues1, $validValues1Strict);
        }
        if (!is_null($validValues2)) {
            $backoff->retryUntil($validValues2, $validValues2Strict);
        }

        // use the backoff to attempt the callback
        $result = $backoff->attempt($wrappedAttempt);

        self::assertSame($expectedToRetry ? 2 : 1, $attemptCount);
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
            'expectedToRetry' => false,
            'expectedResult' => $randInt1,
        ];

        $return = [

            // successful attempts

            'successful attempt' => [],

            // not strict
            'successful attempt - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1,
                'expectedToRetry' => false,
            ],
            'successful attempt - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues2' => $randInt1,
                'expectedToRetry' => false,
            ],
            'successful attempt - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'expectedToRetry' => true,
            ],
            'successful attempt - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 - 1,
                'validValues2' => $randInt1 + 1,
                'expectedToRetry' => true,
            ],

            // strict
            'successful attempt (strict) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1,
                'validValues1Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues1Strict' => true,
                'validValues2' => $randInt1,
                'validValues2Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 + 1,
                'validValues1Strict' => true,
                'expectedToRetry' => true,
            ],
            'successful attempt (strict) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1 - 1,
                'validValues1Strict' => true,
                'validValues2' => $randInt1 + 1,
                'validValues2Strict' => true,
                'expectedToRetry' => true,
            ],

            // strict 2
            'successful attempt (strict 2) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => $randInt1,
                'validValues1Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict 2) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (bool) $randInt1,
                'validValues1Strict' => true,
                'validValues2' => $randInt1,
                'validValues2Strict' => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (strict 2) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (float) $randInt1,
                'validValues1Strict' => true,
                'expectedToRetry' => true,
            ],
            'successful attempt (strict 2) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => (bool) $randInt1,
                'validValues1Strict' => true,
                'validValues2' => (float) $randInt1,
                'validValues2Strict' => true,
                'expectedToRetry' => true,
            ],

            // callback
            'successful attempt (callback) - valid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => true,
                'expectedToRetry' => false,
            ],
            'successful attempt (callback) - valid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => true,
                'validValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedToRetry' => false,
            ],
            'successful attempt (callback) - invalid 1' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => false,
                'expectedToRetry' => true,
            ],
            'successful attempt (callback) - invalid 2' => [
                'attempt' => fn() => $randInt1,
                'validValues1' => fn($value) => false,
                'validValues1Strict' => true, // strictness doesn't matter for callbacks
                'expectedToRetry' => true,
            ],
        ];

        foreach ($return as $index => $value) {
            $return[$index] = array_merge($default, $value);
        }

        return $return;
    }

    /**
     * Test that ->retryWhen() and ->retryUntil() turn the other off.
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
     * Test that ->retryWhen() is not strict by default.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_retry_when_is_not_strict_by_default(): void
    {
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
            return true;
        };

        $backoff = Backoff::noop()->maxAttempts(5)->retryWhen('1');

        $count = 0;
        $backoff->attempt($callback);

        self::assertSame(5, $count); // should have retried 5 times
    }

    /**
     * Test that ->retryUntil() is not strict by default.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_retry_until_is_not_strict_by_default(): void
    {
        $count = 0;
        $callback = function () use (&$count) {
            $count++;
            return true;
        };

        $backoff = Backoff::noop()->maxAttempts(5)->retryUntil('1');

        $count = 0;
        $backoff->attempt($callback);

        self::assertSame(1, $count); // should have retried once
    }
}
