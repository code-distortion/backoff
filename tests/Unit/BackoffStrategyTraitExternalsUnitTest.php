<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\ExponentialBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\DelayTracker;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\TestSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the BackoffStrategyTrait - test the externals, i.e. the constructor parameters and public methods.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyTraitExternalsUnitTest extends PHPUnitTestCase
{
    // test the constructor parameters
    /**
     * Test that the backoff strategy throws an exception when an invalid unit type is passed.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public function test_that_backoff_strategy_throws_an_exception_due_to_invalid_unit_type(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new BackoffStrategy(
            new SequenceBackoffAlgorithm([1]),
            null,
            null,
            null,
            'invalid', // <<< invalid unit type
        );
    }

    /**
     * Test that the backoff strategy uses a backoff algorithm to generate delays.
     *
     * Tests the constructor's $backoffAlgorithm parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_backoff_strategy_uses_backoff_algorithms(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3])
        );
        self::assertSame([1, 2, 3], $strategy->generateTestSequence(5)->getDelays());
    }



    /**
     * Test that the backoff strategy can apply jitter to the delay.
     *
     *  Tests the constructor's $jitter parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_applies_jitter(): void
    {
        $min = mt_rand(0, 10);
        $max = $min + 1;

        $strategy = new BackoffStrategy(
            new FixedBackoffAlgorithm(1),
            new RangeJitter($min, $max), // <<< jitter
        );

        foreach ($strategy->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        $uniqueValues = array_unique($strategy->generateTestSequence(100)->getDelays());
        self::assertGreaterThan(50, count($uniqueValues)); // there should be a good number of unique values
    }



    /**
     * Test that the backoff strategy can apply max attempts to the process.
     *
     * Tests the constructor's $maxAttempts parameter.
     *
     * @test
     * @dataProvider maxAttemptsDataProvider
     *
     * @param integer $maxAttempts The max attempts to test with.
     * @return void
     */
    #[Test]
    #[DataProvider('maxAttemptsDataProvider')]
    public static function test_that_backoff_strategy_applies_max_attempts(int $maxAttempts): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            $maxAttempts, // <<< max attempts
            null,
            null,
            true,
        );

        $count = 0;
        while ($strategy->step(false)) {
            $count++;
        }

        // check the number of attempts that were made
        self::assertSame(max(0, $maxAttempts), $count);

        // check that the strategy has stopped now and won't continue
        self::assertNull($strategy->getDelay());
        self::assertFalse($strategy->step(false));
        self::assertNull($strategy->getDelay());
    }

    /**
     * DataProvider for test_that_backoff_strategy_applies_max_attempts.
     *
     * @return array<array<string,integer>>
     */
    public static function maxAttemptsDataProvider(): array
    {
        return [
            ['maxAttempts' => -1], // check that the strategy doesn't start when the max attempts is less than 0
            ['maxAttempts' => 0], // check that the strategy doesn't start when the max attempts is 0
            ['maxAttempts' => 1],
            ['maxAttempts' => mt_rand(2, 10)],
        ];
    }



    /**
     * Test that the backoff strategy can apply max-delay.
     *
     * Tests the constructor's $maxDelay parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_applies_max_delay(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            5.1, // <<< max-delay
        );
        self::assertSame([1, 2, 3, 4, 5, 5.1, 5.1, 5.1, 5.1, 5.1], $strategy->generateTestSequence(10)->getDelays());
    }



    /**
     * Test that the backoff strategy can apply unit types to the delay.
     *
     * Tests the constructor's $unitType parameter.
     *
     * @test
     * @dataProvider delaysInDifferentUnitsDataProvider
     *
     * @param 'seconds'|'milliseconds'|'microseconds' $unit      The unit type to test with.
     * @param integer|float                           $baseDelay The base delays to test with.
     * @return void
     */
    #[Test]
    #[DataProvider('delaysInDifferentUnitsDataProvider')]
    public static function test_that_backoff_strategy_can_use_the_different_unit_types(
        string $unit,
        int|float $baseDelay
    ): void {

        $s = match ($unit) { // convent back to seconds for the sake of the calculation
            Settings::UNIT_SECONDS => $baseDelay * 1,
            Settings::UNIT_MILLISECONDS => $baseDelay * 0.001,
            Settings::UNIT_MICROSECONDS => $baseDelay * 0.000_001,
        };

        $expectedDelays = [$baseDelay * 1, $baseDelay * 2, $baseDelay * 3, $baseDelay * 4, $baseDelay * 5];
        $expectedDelaysSeconds = [$s * 1, $s * 2, $s * 3, $s * 4, $s * 5];
        $expectedDelaysMs = [$s * 1000, $s * 2000, $s * 3000, $s * 4000, $s * 5000];
        $expectedDelaysUs = [$s * 1_000_000, $s * 2_000_000, $s * 3_000_000, $s * 4_000_000, $s * 5_000_000];

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm($baseDelay),
            null,
            null,
            null,
            $unit, // <<< unit type
        );

        self::assertSame($unit, $strategy->getUnitType());

        $generatedDelays = $strategy->generateTestSequence(5);

        self::assertSame($expectedDelays, $generatedDelays->getDelays());
        self::assertSame($expectedDelaysSeconds, $generatedDelays->getDelaysInSeconds());
        self::assertSame($expectedDelaysMs, $generatedDelays->getDelaysInMs());
        self::assertSame($expectedDelaysUs, $generatedDelays->getDelaysInUs());
    }

    /**
     * DataProvider for test_that_backoff_strategy_can_use_the_different_unit_types.
     *
     * @return array<array<string,int|float|string>>
     */
    public static function delaysInDifferentUnitsDataProvider(): array
    {
        return [
            ['unit' => Settings::UNIT_SECONDS, 'baseDelay' => 0.001],
            ['unit' => Settings::UNIT_SECONDS, 'baseDelay' => 1.0],
            ['unit' => Settings::UNIT_SECONDS, 'baseDelay' => 1000.0],

            ['unit' => Settings::UNIT_MILLISECONDS, 'baseDelay' => 1.0],
            ['unit' => Settings::UNIT_MILLISECONDS, 'baseDelay' => 1000.0],
            ['unit' => Settings::UNIT_MILLISECONDS, 'baseDelay' => 1000_000.0],

            ['unit' => Settings::UNIT_MICROSECONDS, 'baseDelay' => 1000.0],
            ['unit' => Settings::UNIT_MICROSECONDS, 'baseDelay' => 1_000_000.0],
            ['unit' => Settings::UNIT_MICROSECONDS, 'baseDelay' => 1_000_000_000.0],
        ];
    }



    /**
     * Test that the backoff strategy starts with and without the first iteration.
     *
     * Tests the constructor's $runsAtStartOfLoop parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_can_be_triggered_at_the_start_or_end_of_the_loop(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            true, // <<< start before the first attempt
        );
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $strategy->generateTestSequence(10)->getDelays());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false, // <<< start after the first attempt
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)->getDelays());
    }



    /**
     * Test that the backoff strategy can insert an immediate first retry.
     *
     * Tests the constructor's $immediateFirstRetry parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_can_insert_an_immediate_first_retry(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(5, 1),
            null,
            null,
            null,
            null,
            false,
            false, // <<< no immediate retry
        );
        self::assertSame([5, 6, 7, 8, 9, 10, 11, 12, 13, 14], $strategy->generateTestSequence(10)->getDelays());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(5, 1),
            null,
            null,
            null,
            null,
            false,
            true, // <<< insert an immediate retry
        );
        self::assertSame([0, 5, 6, 7, 8, 9, 10, 11, 12, 13], $strategy->generateTestSequence(10)->getDelays());
    }



    /**
     * Test that the backoff strategy disable delays.
     *
     * Tests the constructor's $delaysEnabled parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_can_disable_delays(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true, // <<< enable delays
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)->getDelays());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $strategy->generateTestSequence(10)->getDelays());

        // make sure it still stops when the backoff algorithm says to
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2, 3]),
            null,
            null,
            null,
            null,
            false,
            false,
            false, // <<< disable delays
        );
        self::assertSame([0, 0, 0], $strategy->generateTestSequence(10)->getDelays());
    }



    /**
     * Test that the backoff strategy disables retries.
     *
     * Tests the constructor's $retriesEnabled parameter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_can_disable_retries(): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true,
            true, // <<< enable retries
        );
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $strategy->generateTestSequence(10)->getDelays());

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            null,
            false,
            false,
            true,
            false, // <<< disable retries
        );
        self::assertSame([], $strategy->generateTestSequence(10)->getDelays());
    }











    // test the public methods

    /**
     * Test Backoff's alternative new(…) constructor.
     *
     * Test that the parameters are passed to the BackoffStrategy correctly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_alternative_constructor(): void
    {
        // new()
        $algorithm = new LinearBackoffAlgorithm(1);
        $backoff = BackoffStrategy::new($algorithm);
        self::assertInstanceOf(BackoffStrategy::class, $backoff);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());

        // with various settings
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
        self::assertSame(Settings::UNIT_MILLISECONDS, $backoff->getUnitType());
        self::assertSame($delays->getDelaysInMs(), $delays->getDelays());
        self::assertSame([null, 0, 1, 2, 3, 4, 5, 5], $delays->getDelays());

        // once more with the same values, except this time with FullJitter to check that it's applied
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
        self::assertNotSame([null, 0, 1, 2, 3, 4, 5, 5], $backoff->generateTestSequence(10)->getDelays());

        // again, but with delays disabled
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            new FullJitter(),
            8,
            5,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            false,
            true,
        );
        self::assertNotSame([0, 0, 0, 0, 0, 0, 0, 0], $backoff->generateTestSequence(10)->getDelays());

        // again, but with retries disabled
        $backoff = BackoffStrategy::new(
            new LinearBackoffAlgorithm(1),
            new FullJitter(),
            8,
            5,
            Settings::UNIT_MILLISECONDS,
            true,
            true,
            false,
            false,
        );
        self::assertNotSame([], $backoff->generateTestSequence(10)->getDelays());
    }



    /**
     * Test that the reset method works. Checks that the settings are all reset back to the default "beginning" state.
     *
     * Tests the reset() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_backoff_strategy_can_be_reset(): void
    {
        $baseAdd = 0; // return the retry number up to 5, then stop
        $jitterAdd = 0.1; // have the jitter always add 0.1

        // the base delay is the retry number plus a certain amount, with up to 5 retries
        $backoffCallback = function (int $retryNumber) use (&$baseAdd): int|null {
            return $retryNumber <= 5
                ? $retryNumber + $baseAdd
                : null;
        };
        // the jitter always adds a certain amount
        $jitterCallback = function (int|float $delay) use (&$jitterAdd): float {
            return $delay + $jitterAdd;
        };

        $backoff = new BackoffStrategy(
            new CallbackBackoffAlgorithm($backoffCallback),
            new CallbackJitter($jitterCallback),
        );



        // run the sequence in two parts, so it can be queried in between

        // check that it's ready to start
        self::assertFalse($backoff->hasStopped());
        self::assertTrue($backoff->isFirstAttempt());
        self::assertFalse($backoff->isLastAttempt());

        // perform the first part of the sequence
        // (this is just to prove that getDelay() does change)
        self::assertSame([1.1, 2.1, 3.1], $backoff->generateTestSequence(3)->getDelays());
        self::assertSame(4, $backoff->currentAttemptNumber());
        self::assertSame(3.1, $backoff->getDelay());

        // check where it's at
        self::assertFalse($backoff->hasStopped());
        self::assertFalse($backoff->isFirstAttempt());
        self::assertFalse($backoff->isLastAttempt());

        // perform the second part of the sequence
        self::assertSame([4.1, 5.1], $backoff->generateTestSequence(3)->getDelays());
        self::assertSame(6, $backoff->currentAttemptNumber());
        self::assertNull($backoff->getDelay());

        // check that it's stopped
        self::assertTrue($backoff->hasStopped());
        self::assertFalse($backoff->isFirstAttempt());
        self::assertTrue($backoff->isLastAttempt());



        // reset and start again, with some DIFFERENT numbers
        $backoff->reset();
        $baseAdd = 1; // return a retry number that's 1 higher
        $jitterAdd = 0.2; // have the jitter always add 0.2

        // check the reset worked
        self::assertSame(1, $backoff->currentAttemptNumber());
        self::assertNull($backoff->getDelay());

        // check that it's ready to start (again)
        self::assertFalse($backoff->hasStopped());
        self::assertTrue($backoff->isFirstAttempt());
        self::assertFalse($backoff->isLastAttempt());

        // check the new sequence
        self::assertSame([2.2, 3.2, 4.2, 5.2, 6.2], $backoff->generateTestSequence(10)->getDelays());
        self::assertSame(6, $backoff->currentAttemptNumber());
        self::assertNull($backoff->getDelay());

        // check that it's stopped
        self::assertTrue($backoff->hasStopped());
        self::assertFalse($backoff->isFirstAttempt());
        self::assertTrue($backoff->isLastAttempt());
    }



    /**
     * Check that the backoff strategy's step() method acts properly.
     *
     * - test that step() causes the attempt number to be updated.
     * - test that step() triggers the delay calculation.
     * - test that step() returns true and false properly.
     *
     * @test
     * @dataProvider stepDataProvider
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm       The backoff algorithm to test with.
     * @param integer|null              $maxAttempts            The max attempts to test with.
     * @param boolean                   $runsAtStartOfLoop      Whether the strategy runs at the start of the loop or
     *                                                          not.
     * @param boolean[]                 $expectedStepResults    The expected results from step() or calculate() method.
     * @param array<null|integer>       $expectedAttemptNumbers The expected currentAttemptNumber() results.
     * @param array<integer|float>      $expectedDelays         The expected getDelay() results.
     * @return void
     */
    #[Test]
    #[DataProvider('stepDataProvider')]
    public static function test_the_step_method(
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?int $maxAttempts,
        bool $runsAtStartOfLoop,
        array $expectedStepResults,
        array $expectedAttemptNumbers,
        array $expectedDelays,
    ): void {

        $strategy = new BackoffStrategy(
            $backoffAlgorithm,
            null,
            $maxAttempts,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            $runsAtStartOfLoop,
        );

        for ($count = 0; $count < count($expectedStepResults); $count++) {
            // test that step() causes the attempt number to be updated
            self::assertSame($expectedAttemptNumbers[$count], $strategy->currentAttemptNumber());
            // test that step() triggers the delay calculation
            self::assertSame($expectedDelays[$count], $strategy->getDelay());
            // test that step() returns true and false properly
            self::assertSame($expectedStepResults[$count], $strategy->step());
        }
    }

    /**
     * DataProvider for test_the_step_method.
     *
     * @return array<array<string,boolean|integer|BackoffAlgorithmInterface|array<boolean|null|integer|float>|null>>
     */
    public static function stepDataProvider(): array
    {
        return [

            // runs at the start of the loop
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 0,
                'runsAtStartOfLoop' => true,
                'expectedStepResults' => [false, false, false],
                'expectedAttemptNumbers' => [null, null, null], // doesn't start
                'expectedDelays' => [null, null, null],
            ],
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 1,
                'runsAtStartOfLoop' => true,
                'expectedStepResults' => [true, false, false, false],
                'expectedAttemptNumbers' => [null, 1, 1, 1], // starts, doesn't go past 1
                'expectedDelays' => [null, null, null, null],
            ],
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 5,
                'runsAtStartOfLoop' => true,
                'expectedStepResults' => [true, true, true, true, true, false, false, false],
                'expectedAttemptNumbers' => [null, 1, 2, 3, 4, 5, 5, 5], // starts, doesn't go past 5
                'expectedDelays' => [null, null, 1.0, 1.1, 1.2, 1.3, null, null],
            ],
            [
                'backoffAlgorithm' => new SequenceBackoffAlgorithm([]),
                'maxAttempts' => null,
                'runsAtStartOfLoop' => true,
                'expectedStepResults' => [true, false, false, false],
                'expectedAttemptNumbers' => [null, 1, 1, 1], // starts, doesn't go past 1
                'expectedDelays' => [null, null, null, null],
            ],
            [
                'backoffAlgorithm' => new SequenceBackoffAlgorithm([1, 1.1]),
                'maxAttempts' => null,
                'runsAtStartOfLoop' => true,
                'expectedStepResults' => [true, true, true, false, false, false],
                'expectedAttemptNumbers' => [null, 1, 2, 3, 3, 3], // starts, doesn't go past 3
                'expectedDelays' => [null, null, 1, 1.1, null, null],
            ],

            // runs at the end of the loop
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 0,
                'runsAtStartOfLoop' => false,
                'expectedStepResults' => [false, false],
                'expectedAttemptNumbers' => [null, null], // doesn't start
                'expectedDelays' => [null, null],
            ],
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 1,
                'runsAtStartOfLoop' => false,
                'expectedStepResults' => [false, false, false],
                'expectedAttemptNumbers' => [1, 1, 1], // starts, doesn't go past 1
                'expectedDelays' => [null, null, null],
            ],
            [
                'backoffAlgorithm' => new LinearBackoffAlgorithm(1, 0.1),
                'maxAttempts' => 5,
                'runsAtStartOfLoop' => false,
                'expectedStepResults' => [true, true, true, true, false, false, false],
                'expectedAttemptNumbers' => [1, 2, 3, 4, 5, 5, 5], // starts, doesn't go past 5
                'expectedDelays' => [null, 1.0, 1.1, 1.2, 1.3, null, null],
            ],
            [
                'backoffAlgorithm' => new SequenceBackoffAlgorithm([]),
                'maxAttempts' => null,
                'runsAtStartOfLoop' => false,
                'expectedStepResults' => [false, false, false],
                'expectedAttemptNumbers' => [1, 1, 1], // starts, doesn't go past 1
                'expectedDelays' => [null, null, null],
            ],
            [
                'backoffAlgorithm' => new SequenceBackoffAlgorithm([1, 1.1]),
                'maxAttempts' => null,
                'runsAtStartOfLoop' => false,
                'expectedStepResults' => [true, true, false, false, false],
                'expectedAttemptNumbers' => [1, 2, 3, 3, 3], // starts, doesn't go past 3
                'expectedDelays' => [null, 1, 1.1, null, null],
            ],
        ];
    }

    /**
     * Check that the backoff strategy's step() method actually sleeps (when told to).
     *
     * @test
     * @dataProvider sleepDataProvider
     *
     * @param integer $milliseconds The number of milliseconds to test with.
     * @param boolean $sleep        Whether to sleep or not.
     * @param boolean $expectSleep  Whether to expect a sleep to happen or not.
     * @return void
     */
    #[Test]
    #[DataProvider('sleepDataProvider')]
    public static function test_that_step_sleeps(int $milliseconds, ?bool $sleep, bool $expectSleep): void
    {
        $seconds = $milliseconds / 1000;
        // within a margin of +/- 20% should be plenty
        // note: this timing varies greatly on Windows and somewhat on Mac
        $margin = TestSupport::isWindowsOrMac()
            ? 1.5
            : 1.2;

        $newStrategy = fn() => new BackoffStrategy(
            new SequenceBackoffAlgorithm([$milliseconds]),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // choose a unit that's easy enough to measure, but not too big
        );



        // check whether it sleeps or not using a DelayTracker
        $strategy = $newStrategy();
        $tracker = $strategy->useTracker(); // <<< use the tracker

        !is_null($sleep)
            ? $strategy->step($sleep)
            : $strategy->step();
        self::assertSame($expectSleep ? 1 : 0, $tracker->getSleepCallCount());



        // test whether it sleeps or not by looking at the time spent
        $strategy = $newStrategy();

        $start = microtime(true);
        !is_null($sleep)
            ? $strategy->step($sleep)
            : $strategy->step();
        $diff = microtime(true) - $start;

        if ($expectSleep) {
            // check that it matches within the margin
            self::assertGreaterThan($seconds / $margin, $diff);
            self::assertLessThan($seconds * $margin, $diff);
        } else {
            // check that it didn't sleep
            self::assertLessThan(0.001, $diff);
        }
    }

    /**
     * Check that the backoff strategy's sleep() method actually sleeps.
     *
     * @test
     * @dataProvider sleepDataProvider
     *
     * @param integer $milliseconds The number of milliseconds to test with.
     * @param boolean $sleep        Whether to sleep or not.
     * @param boolean $expectSleep  Whether to expect a sleep to happen or not (ignored, is here because the same data
     *                              provider is used to test the step() method).
     * @return void
     */
    #[Test]
    #[DataProvider('sleepDataProvider')]
    public static function test_that_sleep_sleeps(int $milliseconds, ?bool $sleep, bool $expectSleep): void
    {
        $seconds = $milliseconds / 1000;
        // within a margin of +/- 20% should be plenty
        // note: this timing varies greatly on Windows and somewhat on Mac
        $margin = TestSupport::isWindowsOrMac()
            ? 1.5
            : 1.2;

        $newStrategy = fn() => new BackoffStrategy(
            new SequenceBackoffAlgorithm([$milliseconds]),
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // choose a unit that's easy enough to measure, but not too big
        );



        // check whether it sleeps or not using a DelayTracker
        $strategy = $newStrategy();
        $tracker = $strategy->useTracker(); // <<< use the tracker

        $strategy->step(false);
        $strategy->sleep();
        self::assertSame(1, $tracker->getSleepCallCount());



        // test whether it sleeps or not by looking at the time spent
        $strategy = $newStrategy();

        $strategy->step(false);
        $start = microtime(true);
        $strategy->sleep();
        $diff = microtime(true) - $start;

        // check that it matches within the margin
        self::assertGreaterThan($seconds / $margin, $diff);
        self::assertLessThan($seconds * $margin, $diff);
    }

    /**
     * DataProvider for test_that_step_sleeps.
     *
     * @return array<array<string,integer|boolean|null>>
     */
    public static function sleepDataProvider(): array
    {
        // note: this timing varies greatly on Windows and somewhat on Mac
        $min = TestSupport::isWindowsOrMac()
            ? 400
            : 4;
        $max = TestSupport::isWindowsOrMac()
            ? 500
            : 5;

        return [
            ['milliseconds' => mt_rand($min, $max), 'sleep' => true, 'expectSleep' => true],
            ['milliseconds' => mt_rand($min, $max), 'sleep' => false, 'expectSleep' => false],
            ['milliseconds' => mt_rand($min, $max), 'sleep' => null, 'expectSleep' => true],
        ];
    }



    /**
     * Check that the backoff strategy's sleep() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_sleep_returns_true_or_false_properly(): void
    {
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([0, 1]),
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
        );
        self::assertTrue($strategy->sleep()); // hasn't started yet
        $strategy->step(false);
        self::assertTrue($strategy->sleep()); // first sleep is 0
        $strategy->step(false);
        self::assertTrue($strategy->sleep()); // second sleep is 1
        $strategy->step(false);
        self::assertFalse($strategy->sleep()); // has finished
    }





    // Note: The log related methods are testing separately in BackoffStrategyTraitLoggingUnitTest
    // startOfAttempt()
    // endOfAttempt()
    // logs()
    // currentLog()





    /**
     * Check that the backoff strategy's hasStopped() and canContinue() methods return true/false properly.
     *
     * Tests the hasStopped() method, and the logic that calculates it.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_has_stopped_and_can_continue_returns_true_or_false_properly(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1]);
        $strategy = new BackoffStrategy($algorithm);
        self::assertFalse($strategy->hasStopped());
        self::assertTrue($strategy->canContinue());
        // the first attempt would happen here
        $strategy->step(false);
        // the first delay would occur here
        self::assertFalse($strategy->hasStopped());
        self::assertTrue($strategy->canContinue());
        // the second attempt would happen here
        $strategy->step(false);
        // there are no more delays to occur
        self::assertTrue($strategy->hasStopped()); // has finished
        self::assertFalse($strategy->canContinue()); // has finished



        // check that it should be stopped to begin with when max attempts is 0
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            0, // <<< max attempts
        );
        self::assertTrue($strategy->hasStopped());
        self::assertFalse($strategy->canContinue());
    }





    /**
     * Test that the backoff strategy's ->currentAttemptNumber() returns the current attempt number.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_current_attempt_number_can_be_retrieved(): void
    {
        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            null,
            true, // <<< start before the first attempt
        );

        $attemptNumbers = [];
        while ($strategy->step(false) && !$strategy->hasStopped()) {
            $attemptNumbers[] = $strategy->currentAttemptNumber();
        }
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);
        // check that the current attempt number is still 5
        self::assertSame(5, $strategy->currentAttemptNumber());



        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            null,
            false, // <<< start after the first attempt
        );

        // perform the logic manually to avoid the sleep
        $attemptNumbers = [];
        do {
            $attemptNumbers[] = $strategy->currentAttemptNumber();
        } while ($strategy->step(false) && !$strategy->hasStopped());
        self::assertSame([1, 2, 3, 4, 5], $attemptNumbers);
        // check that the current attempt number is still 5
        self::assertSame(5, $strategy->currentAttemptNumber());
    }

    /**
     * Check that the backoff strategy's isFirstAttempt() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_is_first_attempt_returns_true_or_false_properly(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count === 1, $strategy->isFirstAttempt());
        }

        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count === 1, $strategy->isFirstAttempt());
        } while ($strategy->step());
    }

    /**
     * Check that the backoff strategy's isLastAttempt() method returns true/false properly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_is_last_attempt_returns_true_or_false_properly(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        // when running $strategy->step() at the beginning of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count === 5, $strategy->isLastAttempt());
        }

        // test when stopped = true
        self::assertTrue($strategy->isLastAttempt());



        // when running $strategy->step() at the end of the loop
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            5,
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count === 5, $strategy->isLastAttempt());
        } while ($strategy->step());





        // when running $strategy->step() at the beginning of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            null, // <<< no max attempts
            null,
            null,
            true, // <<< run before the first attempt
        );
        $count = 5;
        while ((--$count > 0) && ($strategy->step())) {
            self::assertFalse($strategy->isLastAttempt());
        }



        // when running $strategy->step() at the end of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            null, // <<< no max attempts
            null,
            null,
            false, // <<< don't run before the first attempt
        );
        $count = 5;
        do {
            self::assertFalse($strategy->isLastAttempt());
        } while ((--$count > 0) && ($strategy->step()));





        // when running $strategy->step() at the beginning of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2]),
            null,
            null, // <<< no max attempts
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            true, // <<< run before the first attempt
        );
        $count = 0;
        while ($strategy->step()) {
            self::assertSame(++$count === 3, $strategy->isLastAttempt());
        }



        // when running $strategy->step() at the end of the loop - with no max-attempts
        $strategy = new BackoffStrategy(
            new SequenceBackoffAlgorithm([1, 2]),
            null,
            null, // <<< no max attempts
            null,
            Settings::UNIT_MICROSECONDS, // make sure the time slept is small
            false, // <<< don't run before the first attempt
        );
        $count = 0;
        do {
            self::assertSame(++$count === 3, $strategy->isLastAttempt());
        } while ($strategy->step());
    }





    /**
     * Test that the getUnitType() method returns the correct unit type.
     *
     * @test
     * @dataProvider unitTypeDataProvider
     *
     * @param string $unitType The unit type to test with.
     * @return void
     */
    #[Test]
    #[DataProvider('unitTypeDataProvider')]
    public static function test_the_retrieval_of_the_unit_type(string $unitType): void
    {
        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            null,
            null,
            $unitType,
        );

        self::assertSame($unitType, $strategy->getUnitType());
    }

    /**
     * DataProvider for test_the_retrieval_of_the_unit_type.
     *
     * @return array<array<string,string>>
     */
    public static function unitTypeDataProvider(): array
    {
        return [
            ['unitType' => Settings::UNIT_SECONDS],
            ['unitType' => Settings::UNIT_MILLISECONDS],
            ['unitType' => Settings::UNIT_MICROSECONDS],
        ];
    }





    /**
     * Test that the backoff strategy returns the previously calculated delay.
     *
     * Tests the getDelay(), getDelayInSeconds(), getDelayInMs() and getDelayInUs() methods.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_previously_calculated_delay_can_be_retrieved(): void
    {
        // when running $strategy->step() at the BEGINNING of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
            null,
            null,
            true,
        );

        // perform the logic manually to avoid the sleep
        while ($strategy->step(false) && !$strategy->hasStopped()) {

            $delays[] = $strategy->getDelay();
            $delaysSeconds[] = $strategy->getDelayInSeconds();
            $delaysMilliseconds[] = $strategy->getDelayInMs();
            $delaysMicroseconds[] = $strategy->getDelayInUs();
        }

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);



        // when running $strategy->step() at the END of the loop
        $delays = [];
        $delaysSeconds = [];
        $delaysMilliseconds = [];
        $delaysMicroseconds = [];

        $strategy = new BackoffStrategy(
            new LinearBackoffAlgorithm(1),
            null,
            5,
        );

        do {
            $delays[] = $strategy->getDelay();
            $delaysSeconds[] = $strategy->getDelayInSeconds();
            $delaysMilliseconds[] = $strategy->getDelayInMs();
            $delaysMicroseconds[] = $strategy->getDelayInUs();

            // perform the logic manually to avoid the sleep
        } while ($strategy->step(false) && !$strategy->hasStopped());

        self::assertSame([null, 1, 2, 3, 4], $delays);
        self::assertSame([null, 1, 2, 3, 4], $delaysSeconds);
        self::assertSame([null, 1000, 2000, 3000, 4000], $delaysMilliseconds);
        self::assertSame([null, 1_000_000, 2_000_000, 3_000_000, 4_000_000], $delaysMicroseconds);
    }





    /**
     * Test that the simulate() methods (simulateInSeconds(), etc) generate the correct delays.
     *
     * Tests the simulate(), simulateInSeconds(), simulateInMs() and simulateInUs() methods.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_simulate_generates_delays(): void
    {
        $aRange1 = [
            1 => 0.001,
            2 => 0.002,
            3 => 0.004,
            4 => 0.008,
            5 => 0.016,
        ];
        $aRange2 = [
            6 => 0.032,
            7 => 0.064,
            8 => 0.128,
            9 => 0.256,
            10 => 0.512,
        ];
        $bRange1 = [
            1 => 1,
            2 => 2,
            3 => 4,
            4 => 8,
            5 => 16,
        ];
        $bRange2 = [
            6 => 32,
            7 => 64,
            8 => 128,
            9 => 256,
            10 => 512,
        ];
        $cRange1 = [
            1 => 1000,
            2 => 2000,
            3 => 4000,
            4 => 8000,
            5 => 16_000,
        ];
        $cRange2 = [
            6 => 32_000,
            7 => 64_000,
            8 => 128_000,
            9 => 256_000,
            10 => 512_000,
        ];

        // test exponential without jitter
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm);

        self::assertSame($bRange2, $strategy->simulate(6, 10));
        self::assertSame($bRange1, $strategy->simulate(1, 5));
        /** @var array<integer|float|null> $delays */
        $delays = $strategy->simulate(1, 100);
        self::assertCount(100, $delays);



        // test a sequence of delays with int, float and null
        $algorithm = new SequenceBackoffAlgorithm([1, 1.5]);
        $strategy = new BackoffStrategy($algorithm);

        self::assertSame(1, $strategy->simulate(1));
        self::assertSame(1.5, $strategy->simulate(2));
        self::assertSame(null, $strategy->simulate(3));

        self::assertSame(1, $strategy->simulateInSeconds(1));
        self::assertSame(1.5, $strategy->simulateInSeconds(2));
        self::assertSame(null, $strategy->simulateInSeconds(3));

        self::assertSame(1000, $strategy->simulateInMs(1));
        self::assertSame(1500.0, $strategy->simulateInMs(2));
        self::assertSame(null, $strategy->simulateInMs(3));

        self::assertSame(1_000_000, $strategy->simulateInUs(1));
        self::assertSame(1_500_000.0, $strategy->simulateInUs(2));
        self::assertSame(null, $strategy->simulateInUs(3));

        // invalid ranges
        self::assertSame([], $strategy->simulate(0, 20));
        self::assertSame([], $strategy->simulate(0, 0));
        self::assertSame([], $strategy->simulate(-1, 20));
        self::assertSame([], $strategy->simulate(-2, -1));
        self::assertSame([], $strategy->simulate(10, 9));



        // test exponential with jitter
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy($algorithm, new FullJitter());

        $delays2a = $strategy->simulate(6, 10);
        $delays1a = $strategy->simulate(1, 5);
        $delays2b = $strategy->simulate(6, 10); // again
        $delays1b = $strategy->simulate(1, 5); // again

        self::assertNotSame($bRange2, $strategy->simulate(6, 10));
        self::assertNotSame($bRange1, $strategy->simulate(1, 5));

        self::assertSame($delays1a, $delays1b); // just check they match
        self::assertSame($delays2a, $delays2b);



        // test with a different unit-of-measure
        $algorithm = new ExponentialBackoffAlgorithm(1);
        $strategy = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS,
        );

        self::assertSame($bRange2, $strategy->simulate(6, 10));
        self::assertSame($bRange1, $strategy->simulate(1, 5));

        self::assertSame($aRange2, $strategy->simulateInSeconds(6, 10));
        self::assertSame($aRange1, $strategy->simulateInSeconds(1, 5));

        self::assertSame($bRange2, $strategy->simulateInMs(6, 10));
        self::assertSame($bRange1, $strategy->simulateInMs(1, 5));

        self::assertSame($cRange2, $strategy->simulateInUs(6, 10));
        self::assertSame($cRange1, $strategy->simulateInUs(1, 5));
    }




    /**
     * Test that the strategy tracks delays.
     *
     * Tests the useTracker() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_use_tracker_method(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1, 2, 3]);
        $strategy = new BackoffStrategy($algorithm);
        $delays = $strategy->useTracker();

        $start = microtime(true);

        // step 4 times (more than the 3, which is when retries actually stop)
        $strategy->step();
        $strategy->step();
        $strategy->step();
        $strategy->step();

        $diff = microtime(true) - $start;

        self::assertInstanceOf(DelayTracker::class, $delays);
        self::assertSame([1, 2, 3], $delays->getDelays());
        self::assertSame([1, 2, 3], $delays->getDelaysInSeconds());
        self::assertSame([1000, 2000, 3000], $delays->getDelaysInMs());
        self::assertSame([1_000_000, 2_000_000, 3_000_000], $delays->getDelaysInUs());
        self::assertSame(3, $delays->getSleepCallCount());
        self::assertSame(3, $delays->getActualTimesSlept());

        // check that it didn't actually sleep
        self::assertLessThan(0.001, $diff);
    }

    /**
     * Test aspects of the generateTestSequence() method.
     *
     * Tests the generateTestSequence() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_strategy_generate_test_sequence_method(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1, 2, 3]); // only 3 retries
        $strategy = new BackoffStrategy($algorithm);

        $start = microtime(true);
        $delays = $strategy->generateTestSequence(10); // more than the actual 3 retries
        $diff = microtime(true) - $start;

        // test edge-cases inside ->sleep()
        // this is to make sure that sleep is called the correct number of times as the sequence is exhausted
        self::assertSame(3, $delays->getSleepCallCount());
        self::assertSame(3, $delays->getActualTimesSlept());

        // check that it didn't actually sleep
        self::assertLessThan(0.001, $diff);
    }





    /**
     * Test the BackoffStrategyTrait's public methods, to confirm which ones "start" the backoff process.
     *
     * @test
     * @dataProvider methodsThatMightStartTheBackoffProcessDataProvider
     *
     * @param callable $callABackoffMethod          A callback to call a method that starts the backoff.
     * @param boolean  $expectedToHaveStarted       Whether the backoff is expected to have started or not.
     * @param integer  $expectedNumberOfAttemptLogs The number of AttemptLogs expected to be present.
     * @return void
     */
    #[Test]
    #[DataProvider('methodsThatMightStartTheBackoffProcessDataProvider')]
    public static function test_which_methods_start_the_backoff_process(
        callable $callABackoffMethod,
        bool $expectedToHaveStarted,
        int $expectedNumberOfAttemptLogs,
    ): void {

        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            null,
            true, // <<< run before the first attempt
        );

        // perform some steps to begin with
        $backoff->step(false);
        $backoff->startOfAttempt()->endOfAttempt();
        $backoff->step(false);
        $backoff->startOfAttempt()->endOfAttempt();

        // reset the Backoff so then the logs can be checked
        $backoff->reset();

        // even though the Backoff has been reset, the logs aren't truncated until the backoff is "started" again below
        // confirm that count here is 2 here as expected
        self::assertCount(2, $backoff->logs());

        $callABackoffMethod($backoff);

        self::assertSame($expectedToHaveStarted, $backoff->currentAttemptNumber() === 1);

        // check if the logs have been reset now
        // this happens when the backoff "starts" again, which truncates the logs
        self::assertCount($expectedNumberOfAttemptLogs, $backoff->logs());
    }

    /**
     * DataProvider for test_which_methods_start_the_backoff_process.
     *
     * @return array<string,array<string,boolean|callable|integer>>
     */
    public static function methodsThatMightStartTheBackoffProcessDataProvider(): array
    {
        // expectedNumberOfAttemptLogs = …
        // 0 - if it does start the backoff
        // 1 - for ->startOfAttempt() which starts the Backoff, but also adds a new one
        // 2 - if it doesn't start the backoff
        return [
            'reset()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->reset(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'step()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->step(false),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'sleep()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->sleep(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'startOfAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->startOfAttempt(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 1, // this starts the Backoff, but also adds a new one
            ],
            // ->endOfAttempt() will throw an exception if ->startOfAttempt() isn't called first
            // so, it's not included here
//            'endOfAttempt()' => [
//                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->endOfAttempt(),
//                'expectedToHaveStarted' => false,
//                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
//            ],
            'logs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->logs(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'currentLog()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->currentLog(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'hasStopped()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->hasStopped(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'canContinue()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->hasStopped(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'currentAttemptNumber()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->currentAttemptNumber(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'isFirstAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->isFirstAttempt(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'isLastAttempt()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->isLastAttempt(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'getUnitType()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getUnitType(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
            'getDelay()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelay(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'getDelayInSeconds()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInSeconds(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'getDelayInMs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInMs(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'getDelayInUs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->getDelayInUs(),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'simulate()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulate(1),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'simulateInSeconds()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInSeconds(1),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'simulateInMs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInMs(1),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'simulateInUs()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->simulateInUs(1),
                'expectedToHaveStarted' => true,
                'expectedNumberOfAttemptLogs' => 0, // this method does start the backoff
            ],
            'useTracker()' => [
                'callABackoffMethod' => fn(BackoffStrategy $backoff) => $backoff->useTracker(),
                'expectedToHaveStarted' => false,
                'expectedNumberOfAttemptLogs' => 2, // this method doesn't start the backoff
            ],
        ];
    }



    /**
     * Test that BackoffStrategy populates the $firstAttemptOccurredAt property in the AttemptLogs correctly.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_the_first_attempt_occurred_at_value_is_set_correctly(): void
    {
        $algorithm = new SequenceBackoffAlgorithm([1, 2]);
        $backoff = new BackoffStrategy($algorithm);

        do {
            $backoff->startOfAttempt();
        } while ($backoff->step(false));

        $logs = $backoff->logs();

        // check each AttemptLog's firstAttemptOccurredAt() value
        self::assertSame($logs[0]->firstAttemptOccurredAt(), $logs[1]->firstAttemptOccurredAt());
        self::assertSame($logs[0]->firstAttemptOccurredAt(), $logs[2]->firstAttemptOccurredAt());

        // check each AttemptLog's thisAttemptOccurredAt() value
        self::assertSame($logs[0]->firstAttemptOccurredAt(), $logs[0]->thisAttemptOccurredAt());
        self::assertNotSame($logs[0]->firstAttemptOccurredAt(), $logs[1]->thisAttemptOccurredAt());
        self::assertNotSame($logs[0]->firstAttemptOccurredAt(), $logs[2]->thisAttemptOccurredAt());
    }
}
