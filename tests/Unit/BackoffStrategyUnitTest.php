<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Strategies\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;

/**
 * Test the Backoff classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffHandlerUnitTest extends PHPUnitTestCase
{
    /**
     * Test Backoff's alternative constructors.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_alternative_constructors(): void
    {
        // new()
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
        );
        self::assertInstanceOf(BackoffStrategy::class, $backoff);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // with settings
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            null,
            8,
            5,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            true,
            true,
        );
        $delays = $backoff->generateTestSequence(20);
        self::assertSame([null, 0, 1, 2, 3, 4, 5, 5], $delays['delay']);

        // these should be equal given that the unitType was set to milliseconds
        self::assertSame($delays['delayInMs'], $delays['delay']);

        // once more, to check that the FullJitter is applied
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            new FullJitter(),
            8,
            5,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            true,
            true,
        );
        self::assertNotSame([null, 0, 1, 2, 3, 4, 5, 5, 5], $backoff->generateTestSequence(20)['delay']);



        // fixed
        $backoff = BackoffStrategy::fixed(5);
        self::assertSame([5, 5, 5, 5, 5], $backoff->generateTestSequence(5)['delay']);



        // linear
        $backoff = BackoffStrategy::linear(5);
        self::assertSame([5, 10, 15, 20, 25], $backoff->generateTestSequence(5)['delay']);

        $backoff = BackoffStrategy::linear(5, 10);
        self::assertSame([5, 15, 25, 35, 45], $backoff->generateTestSequence(5)['delay']);



        // exponential
        $backoff = BackoffStrategy::exponential(1);
        self::assertSame([1, 2, 4, 8, 16], $backoff->generateTestSequence(5)['delay']);

        $backoff = BackoffStrategy::exponential(1, 1.5);
        self::assertSame([1.0, 1.5, 2.25, 3.375, 5.0625], $backoff->generateTestSequence(5)['delay']);



        // polynomial
        $backoff = BackoffStrategy::polynomial(1);
        self::assertSame(
            [1, 4, 9, 16, 25],
            $backoff->generateTestSequence(5)['delay']
        );

        $backoff = BackoffStrategy::polynomial(1, 1.5);
        self::assertSame(
            [1.0, 2.8284271247461903, 5.196152422706632, 8.0, 11.180339887498949],
            $backoff->generateTestSequence(5)['delay']
        );



        // fibonacci
        $backoff = BackoffStrategy::fibonacci(1);
        self::assertSame([1, 2, 3, 5, 8], $backoff->generateTestSequence(5)['delay']);

        $backoff = BackoffStrategy::fibonacci(1, false);
        self::assertSame([1, 2, 3, 5, 8], $backoff->generateTestSequence(5)['delay']);

        $backoff = BackoffStrategy::fibonacci(1, true);
        self::assertSame([1, 1, 2, 3, 5], $backoff->generateTestSequence(5)['delay']);



        // decorrelated
        $initialDelay = 1;
        $multiplier = 3;
        $backoff = BackoffStrategy::decorrelated($initialDelay, $multiplier);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }



        // random
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $backoff = BackoffStrategy::random($min, $max);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }



        // sequence
        $backoff = BackoffStrategy::sequence([9, 8, 7, 6, 5]);
        self::assertSame([9, 8, 7, 6, 5], $backoff->generateTestSequence(5)['delay']);



        // callback
        $callback = fn($retryNumber) => $retryNumber < 5
            ? $retryNumber
            : null;
        $backoff = BackoffStrategy::callback($callback);
        self::assertSame([1, 2, 3, 4], $backoff->generateTestSequence(10)['delay']);



        // custom
        $backoff = BackoffStrategy::custom(new LinearBackoffAlgorithm(5, 10));
        self::assertSame([5, 15, 25, 35, 45], $backoff->generateTestSequence(5)['delay']);



        // noop
        $backoff = BackoffStrategy::noop();
        self::assertSame([0, 0, 0, 0, 0], $backoff->generateTestSequence(5)['delay']);



        // none
        $backoff = BackoffStrategy::none();
        self::assertSame([], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that Backoff can set Jitter.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_jitter_can_be_set(): void
    {
        // custom jitter
        $jitter = new RangeJitter(0.75, 1.25);
        $backoff = BackoffStrategy::fixed(4)->customJitter($jitter);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(3, $delay);
            self::assertLessThanOrEqual(5, $delay);
        }

        // full jitter
        $backoff = BackoffStrategy::fixed(4)->fullJitter();
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(4, $delay);
        }

        // equal jitter
        $backoff = BackoffStrategy::fixed(4)->equalJitter();
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(2, $delay);
            self::assertLessThanOrEqual(4, $delay);
        }

        // jitter range
        $backoff = BackoffStrategy::fixed(4)->jitterRange(0.75, 1.25);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(3, $delay);
            self::assertLessThanOrEqual(5, $delay);
        }

        // callback jitter
        $callback = fn(int $delay) => mt_rand($delay, $delay + 3);
        $backoff = BackoffStrategy::fixed(4)->jitterCallback($callback);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertTrue(in_array($delay, [4, 5, 6, 7]));
        }

        // no jitter
        $backoff = BackoffStrategy::fixed(4)->fullJitter()->noJitter();
        self::assertSame([4, 4, 4, 4, 4], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that Backoff can set the max attempts.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_max_attempts_can_be_set(): void
    {
        // max attempts = 0
        $backoff = BackoffStrategy::linear(1)->maxAttempts(0);
        self::assertSame([], $backoff->generateTestSequence(10)['delay']);

        // max attempts
        $backoff = BackoffStrategy::linear(1)->maxAttempts(8);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $backoff->generateTestSequence(10)['delay']);

        // no max attempts
        $backoff = BackoffStrategy::linear(1)->maxAttempts(8)->noMaxAttempts();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);



        // check that stopped is reassessed when maxAttempts() is called - from 0 to 0
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->maxAttempts(0) // <<<
            ->runsBeforeFirstAttempt();
        while ($backoff->step()) {
        }
        self::assertCount(0, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 0 to 1
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->maxAttempts(1) // <<<
            ->runsBeforeFirstAttempt();
        while ($backoff->step()) {
        }
        self::assertCount(1, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to 0
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->maxAttempts(0) // <<<
            ->runsBeforeFirstAttempt();
        while ($backoff->step()) {
        }
        self::assertCount(0, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to 1
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->maxAttempts(1) // <<<
            ->runsBeforeFirstAttempt();
        while ($backoff->step()) {
        }
        self::assertCount(1, $backoff->logs());



        // check that stopped is reassessed when nsAttempts() is called - from 0 to null
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->noMaxAttempts() // <<<
            ->runsBeforeFirstAttempt();
        $attempts = 5;
        while (($attempts-- > 0) && ($backoff->step())) {
        }
        self::assertCount(5, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to null
        $backoff = BackoffStrategy::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->noMaxAttempts() // <<<
            ->runsBeforeFirstAttempt();
        $attempts = 5;
        while (($attempts-- > 0) && ($backoff->step())) {
        }
        self::assertCount(5, $backoff->logs());
    }



    /**
     * Test that Backoff can set the max-delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_max_delay_can_be_set(): void
    {
        // max attempts
        $backoff = BackoffStrategy::linear(1)->maxDelay(5);
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $backoff->generateTestSequence(10)['delay']);

        // no max attempts
        $backoff = BackoffStrategy::linear(1)->maxDelay(5)->noMaxDelay();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test that Backoff can set the unit-of-measure.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_unit_of_measure_can_be_set(): void
    {
        // unit - Settings::UNIT_SECONDS
        $backoff = BackoffStrategy::linear(1)->unit(Settings::UNIT_SECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInSeconds']);

        // unit - Settings::UNIT_MILLISECONDS
        $backoff = BackoffStrategy::linear(1)->unit(Settings::UNIT_MILLISECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // unit - Settings::UNIT_MICROSECONDS
        $backoff = BackoffStrategy::linear(1)->unit(Settings::UNIT_MICROSECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInUs']);

        // seconds
        $backoff = BackoffStrategy::linear(1)->unitSeconds();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInSeconds']);

        // milliseconds
        $backoff = BackoffStrategy::linear(1)->unitMs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // microseconds
        $backoff = BackoffStrategy::linear(1)->unitUs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInUs']);
    }



    /**
     * Test that Backoff can include the initial attempt.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_initial_attempt_can_be_included(): void
    {
        // include the initial attempt
        $backoff = BackoffStrategy::linear(1)->runsBeforeFirstAttempt();
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = BackoffStrategy::linear(1)->runsBeforeFirstAttempt(true);
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = BackoffStrategy::linear(1)->runsBeforeFirstAttempt(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't include the initial attempt
        $backoff = BackoffStrategy::linear(1)->runsBeforeFirstAttempt()->runsAfterFirstAttempt();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test that Backoff can insert a 0 delay as the first delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_a_0_delay_can_be_inserted_as_the_first_delay(): void
    {
        // insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->immediateFirstRetry();
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->immediateFirstRetry(true);
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->immediateFirstRetry(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->immediateFirstRetry()->noImmediateFirstRetry();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test that Backoff can enable and disable the delay.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_the_delay_can_be_enabled_and_disabled(): void
    {
        // enable the delay
        $backoff = BackoffStrategy::linear(1)->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable the delay
        $backoff = BackoffStrategy::linear(1)->onlyDelayWhen(false);
        self::assertSame([0, 0, 0, 0, 0], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable the delay
        $backoff = BackoffStrategy::linear(1)->onlyDelayWhen(false)->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that Backoff can enable and disable retries.
     *
     * @test
     *
     * @return void
     */
    public static function test_that_retries_can_be_enabled_and_disabled(): void
    {
        // enable retries
        $backoff = BackoffStrategy::linear(1)->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable retries
        $backoff = BackoffStrategy::linear(1)->onlyRetryWhen(false);
        self::assertSame([], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable retries
        $backoff = BackoffStrategy::linear(1)->onlyRetryWhen(false)->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that Backoff throws exceptions when being changed after starting.
     *
     * @test
     * @dataProvider updateBackoffCallbackProvider
     *
     * @param callable $updateBackoffCallback The callback to update the backoff.
     * @return void
     */
    public function test_that_exceptions_are_thrown_when_changed_after_starting(callable $updateBackoffCallback): void
    {
        $this->expectException(BackoffRuntimeException::class);

        $backoff = BackoffStrategy::fixed(1)->unitUs();
        $backoff->step();
        $updateBackoffCallback($backoff);
    }

    /**
     * DataProvider for test_that_exceptions_are_thrown_after_starting.
     *
     * @return array
     */
    public static function updateBackoffCallbackProvider(): array
    {
        return [
            [fn(BackoffStrategy $backoff) => $backoff->fullJitter()],
            [fn(BackoffStrategy $backoff) => $backoff->equalJitter()],
            [fn(BackoffStrategy $backoff) => $backoff->jitterRange(0, 1)],
            [fn(BackoffStrategy $backoff) => $backoff->jitterCallback(fn(int $delay) => $delay)],
            [fn(BackoffStrategy $backoff) => $backoff->customJitter(new FullJitter())],
            [fn(BackoffStrategy $backoff) => $backoff->noJitter()],

            [fn(BackoffStrategy $backoff) => $backoff->maxAttempts(4)],
            [fn(BackoffStrategy $backoff) => $backoff->noMaxAttempts()],

            [fn(BackoffStrategy $backoff) => $backoff->maxDelay(100)],
            [fn(BackoffStrategy $backoff) => $backoff->noMaxDelay()],

            [fn(BackoffStrategy $backoff) => $backoff->unit(Settings::UNIT_SECONDS)],
            [fn(BackoffStrategy $backoff) => $backoff->unitSeconds()],
            [fn(BackoffStrategy $backoff) => $backoff->unitMs()],
            [fn(BackoffStrategy $backoff) => $backoff->unitUs()],

            [fn(BackoffStrategy $backoff) => $backoff->runsBeforeFirstAttempt()],
            [fn(BackoffStrategy $backoff) => $backoff->runsAfterFirstAttempt()],

            [fn(BackoffStrategy $backoff) => $backoff->immediateFirstRetry()],
            [fn(BackoffStrategy $backoff) => $backoff->noImmediateFirstRetry()],

            [fn(BackoffStrategy $backoff) => $backoff->onlyDelayWhen(true)],
            [fn(BackoffStrategy $backoff) => $backoff->onlyRetryWhen(true)],
        ];
    }
}
