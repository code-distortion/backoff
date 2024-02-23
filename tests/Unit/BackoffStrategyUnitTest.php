<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;

/**
 * Test the Backoff classes.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyUnitTest extends PHPUnitTestCase
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
            4,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            true,
            true,
        );
        self::assertNotSame([null, 0, 1, 2, 3, 4, 4, 4], $backoff->generateTestSequence(10)['delay']);



        // fixed
        $backoff = BackoffStrategy::fixed(5);
        self::assertSame([5, 5, 5, 5], $backoff->generateTestSequence(10)['delay']);

        // fixed - in milliseconds
        $backoff = BackoffStrategy::fixedMs(5);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // fixed - in microseconds
        $backoff = BackoffStrategy::fixedUs(5);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // linear
        $backoff = BackoffStrategy::linear(5);
        self::assertSame([5, 10, 15, 20], $backoff->generateTestSequence(10)['delay']);

        $backoff = BackoffStrategy::linear(5, 10);
        self::assertSame([5, 15, 25, 35], $backoff->generateTestSequence(10)['delay']);

        // linear - in milliseconds
        $backoff = BackoffStrategy::linearMs(5);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([5, 10, 15, 20], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // linear - in microseconds
        $backoff = BackoffStrategy::linearUs(5);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([5, 10, 15, 20], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // exponential
        $backoff = BackoffStrategy::exponential(1);
        self::assertSame([1, 2, 4, 8], $backoff->generateTestSequence(10)['delay']);

        $backoff = BackoffStrategy::exponential(1, 1.5);
        self::assertSame([1.0, 1.5, 2.25, 3.375], $backoff->generateTestSequence(10)['delay']);

        // exponential - in milliseconds
        $backoff = BackoffStrategy::exponentialMs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 4, 8], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // exponential - in microseconds
        $backoff = BackoffStrategy::exponentialUs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 4, 8], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // polynomial
        $backoff = BackoffStrategy::polynomial(1);
        self::assertSame([1, 4, 9, 16], $backoff->generateTestSequence(10)['delay']);

        $backoff = BackoffStrategy::polynomial(1, 1.5);
        self::assertSame(
            [1.0, 2.8284271247461903, 5.196152422706632, 8.0],
            $backoff->generateTestSequence(10)['delay']
        );

        // polynomial - in milliseconds
        $backoff = BackoffStrategy::polynomialMs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 4, 9, 16], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // polynomial - in microseconds
        $backoff = BackoffStrategy::polynomialUs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 4, 9, 16], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // fibonacci
        $backoff = BackoffStrategy::fibonacci(1);
        self::assertSame([1, 2, 3, 5], $backoff->generateTestSequence(10)['delay']);

        $backoff = BackoffStrategy::fibonacci(1, false);
        self::assertSame([1, 2, 3, 5], $backoff->generateTestSequence(10)['delay']);

        $backoff = BackoffStrategy::fibonacci(1, true);
        self::assertSame([1, 1, 2, 3], $backoff->generateTestSequence(10)['delay']);

        // fibonacci - in milliseconds
        $backoff = BackoffStrategy::fibonacciMs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // fibonacci - in microseconds
        $backoff = BackoffStrategy::fibonacciUs(1);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // decorrelated
        $initialDelay = 1;
        $multiplier = 3;
        $backoff = BackoffStrategy::decorrelated($initialDelay, $multiplier);

        // check max-attempts first
        self::assertCount(4, $backoff->generateTestSequence(10)['delay']);

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        // decorrelated - in milliseconds
        $backoff = BackoffStrategy::decorrelatedMs(1);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // decorrelated - in microseconds
        $backoff = BackoffStrategy::decorrelatedUs(1);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // random
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $backoff = BackoffStrategy::random($min, $max);

        // check max-attempts first
        self::assertCount(4, $backoff->generateTestSequence(10)['delay']);

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        // random - in milliseconds
        $backoff = BackoffStrategy::randomMs($min, $max);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // random - in microseconds
        $backoff = BackoffStrategy::randomUs($min, $max);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // sequence
        $backoff = BackoffStrategy::sequence([9, 8, 7, 6, 5]);
        self::assertSame([9, 8, 7, 6, 5], $backoff->generateTestSequence(5)['delay']);

        // sequence - in milliseconds
        $backoff = BackoffStrategy::sequenceMs([9, 8, 7, 6, 5]);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // sequence - in microseconds
        $backoff = BackoffStrategy::sequenceUs([9, 8, 7, 6, 5]);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // callback
        $callback = fn($retryNumber) => $retryNumber < 4
            ? $retryNumber
            : null;
        $backoff = BackoffStrategy::callback($callback);
        self::assertSame([1, 2, 3], $backoff->generateTestSequence(10)['delay']);

        // callback - in milliseconds
        $backoff = BackoffStrategy::callbackMs($callback);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 3], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // callback - in microseconds
        $backoff = BackoffStrategy::callbackUs($callback);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([1, 2, 3], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // custom
        $algorithm = new LinearBackoffAlgorithm(5, 10);
        $backoff = BackoffStrategy::custom($algorithm);
        self::assertSame([5, 15, 25, 35], $backoff->generateTestSequence(5)['delay']);

        // custom - in milliseconds
        $backoff = BackoffStrategy::customMs($algorithm);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([5, 15, 25, 35], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // custom - in microseconds
        $backoff = BackoffStrategy::customUs($algorithm);
        $delays = $backoff->generateTestSequence(10);
        self::assertSame([5, 15, 25, 35], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // noop
        $backoff = BackoffStrategy::noop();
        self::assertSame([0, 0, 0, 0], $backoff->generateTestSequence(5)['delay']);



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
        $backoff = BackoffStrategy::fixed(4)->noMaxAttempts()->fullJitter()->noJitter();
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
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->maxDelay(5);
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $backoff->generateTestSequence(10)['delay']);

        // no max attempts
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->maxDelay(5)->noMaxDelay();
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
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->runsBeforeFirstAttempt();
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->runsBeforeFirstAttempt(true);
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->runsBeforeFirstAttempt(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't include the initial attempt
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->runsBeforeFirstAttempt()->runsAfterFirstAttempt();
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
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->immediateFirstRetry();
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->immediateFirstRetry(true);
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->immediateFirstRetry(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't insert a 0 delay
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->immediateFirstRetry()->noImmediateFirstRetry();
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
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable the delay
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyDelayWhen(false);
        self::assertSame([0, 0, 0, 0, 0], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable the delay
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyDelayWhen(false)->onlyDelayWhen(true);
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
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable retries
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyRetryWhen(false);
        self::assertSame([], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable retries
        $backoff = BackoffStrategy::linear(1)->noMaxAttempts()->onlyRetryWhen(false)->onlyRetryWhen(true);
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
