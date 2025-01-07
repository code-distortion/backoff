<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use CodeDistortion\Backoff\Tests\Unit\Support\BackoffStrategy;
use CodeDistortion\Backoff\Tests\Unit\Support\TestSupport;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test how the BackoffStrategyTrait logs the attempts.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffStrategyTraitLoggingUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the BaseBackoffStrategy generates AttemptLog log objects properly.
     *
     * This tests different situations including:
     * - running step() before the loop,
     * - after the loop,
     * - calling ->currentLog() inside the loop
     *
     * @test
     * @dataProvider logGeneratorDataProvider
     *
     * @param callable $generateLogs A callback to run through a loop and generate logs.
     * @return void
     */
    #[Test]
    #[DataProvider('logGeneratorDataProvider')]
    public static function test_backoff_generates_logs_properly(callable $generateLogs): void
    {
        $start = new DateTime();
        /** @var AttemptLog[] $logs */
        $logs = $generateLogs();

        $makeNumeric = fn(int|float|null $value): int|float => is_numeric($value)
            ? $value
            : 0;

        // note: this timing varies greatly on Windows and somewhat on Mac
        $timespan = TestSupport::isWindowsOrMac()
            ? 0.1
            : 0.01;



        self::assertCount(3, $logs);
        self::assertSame([0, 1, 2], array_keys($logs));



        // log 1

        self::assertInstanceOf(AttemptLog::class, $logs[0]);

        self::assertSame(1, $logs[0]->attemptNumber());
        self::assertSame(3, $logs[0]->maxAttempts());

        $firstAttemptOccurredAt1 = $logs[0]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt1 = $logs[0]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[0]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[0]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual($timespan, Support::timeDiff($start, $thisAttemptOccurredAt1)); // happened recently
        self::assertSame(0.0, Support::timeDiff($firstAttemptOccurredAt1, $thisAttemptOccurredAt1));

        self::assertLessThan(1000, $logs[0]->workingTime());
        self::assertLessThan(1000, $logs[0]->overallWorkingTime());

        self::assertNull($logs[0]->prevDelay());
        self::assertSame(1000, $logs[0]->nextDelay());
        self::assertNull($logs[0]->overallDelay());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[0]->unitType());



        // log 2

        self::assertInstanceOf(AttemptLog::class, $logs[1]);

        self::assertSame(2, $logs[1]->attemptNumber());
        self::assertSame(3, $logs[1]->maxAttempts());

        $firstAttemptOccurredAt2 = $logs[1]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt2 = $logs[1]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[1]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[1]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual($timespan, Support::timeDiff($start, $thisAttemptOccurredAt2)); // happened recently
        self::assertGreaterThan(0, Support::timeDiff($firstAttemptOccurredAt2, $thisAttemptOccurredAt2));
        self::assertLessThanOrEqual($timespan, Support::timeDiff($firstAttemptOccurredAt2, $thisAttemptOccurredAt2));

        self::assertLessThan(1000, $logs[1]->workingTime());
        self::assertLessThan(1000, $logs[1]->overallWorkingTime());

        self::assertSame(1000, $logs[1]->prevDelay());
        self::assertSame(2000, $logs[1]->nextDelay());
        self::assertSame(1000, $logs[1]->overallDelay());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[1]->unitType());

        // overall working time should be the sum of the individual working times
        self::assertSame(
            $logs[1]->overallWorkingTime(),
            $makeNumeric($logs[0]->workingTime()) + $makeNumeric($logs[1]->workingTime())
        );



        // log 3

        self::assertInstanceOf(AttemptLog::class, $logs[2]);

        self::assertSame(3, $logs[2]->attemptNumber());
        self::assertSame(3, $logs[2]->maxAttempts());

        $firstAttemptOccurredAt3 = $logs[2]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt3 = $logs[2]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[2]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[2]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual($timespan, Support::timeDiff($start, $thisAttemptOccurredAt3)); // happened recently
        self::assertGreaterThan(0, Support::timeDiff($firstAttemptOccurredAt3, $thisAttemptOccurredAt3));
        self::assertLessThanOrEqual($timespan, Support::timeDiff($firstAttemptOccurredAt3, $thisAttemptOccurredAt3));

        self::assertLessThan(1000, $logs[2]->workingTime());
        self::assertLessThan(1000, $logs[2]->overallWorkingTime());

        self::assertSame(2000, $logs[2]->prevDelay());
        self::assertNull($logs[2]->nextDelay());
        self::assertSame(3000, $logs[2]->overallDelay());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[2]->unitType());

        // overall delay time should be the sum of the individual delay times
        self::assertSame($logs[2]->overallDelay(), $logs[1]->prevDelay() + $logs[2]->prevDelay());
    }

    /**
     * DataProvider for test_backoff_generates_logs_properly.
     *
     * @return array<array<callable>>
     */
    public static function logGeneratorDataProvider(): array
    {
        $return = [];


        $algorithm = new LinearBackoffAlgorithm(1000);
        $newBeforeFirstAttemptBackoff = fn() => new BackoffStrategy(
            $algorithm,
            null,
            3,
            null,
            Settings::UNIT_MICROSECONDS,
            true, // <<< run before the first attempt
        );
        $newAfterFirstAttemptBackoff = fn() => new BackoffStrategy(
            $algorithm,
            null,
            3,
            null,
            Settings::UNIT_MICROSECONDS,
        );



        // $backoff->step() called at the BEGINNING of the loop
        $callback = function () use ($newBeforeFirstAttemptBackoff) {
            $backoff = $newBeforeFirstAttemptBackoff();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $backoff->startOfAttempt()->endOfAttempt();
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newBeforeFirstAttemptBackoff) {
            $backoff = $newBeforeFirstAttemptBackoff();
            $backoff->reset();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $backoff->startOfAttempt()->endOfAttempt();
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newBeforeFirstAttemptBackoff) {
            $backoff = $newBeforeFirstAttemptBackoff();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $backoff->startOfAttempt()->endOfAttempt();
                $backoff->currentLog(); // <<< calls currentLog() inside the loop
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newBeforeFirstAttemptBackoff) {
            $backoff = $newBeforeFirstAttemptBackoff();
            $backoff->reset();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $backoff->startOfAttempt()->endOfAttempt();
                $backoff->currentLog(); // <<< calls currentLog() inside the loop
            }
            return $backoff->logs();
        };
        $return[] = [$callback];



        // $backoff->step() called at the END of the loop
        $callback = function () use ($newAfterFirstAttemptBackoff) {
            $backoff = $newAfterFirstAttemptBackoff();
            $attempts = 10;
            do {
                $backoff->startOfAttempt()->endOfAttempt();
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newAfterFirstAttemptBackoff) {
            $backoff = $newAfterFirstAttemptBackoff();
            $backoff->reset();
            $attempts = 10;
            do {
                $backoff->startOfAttempt()->endOfAttempt();
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newAfterFirstAttemptBackoff) {
            $backoff = $newAfterFirstAttemptBackoff();
            $attempts = 10;
            do {
                $backoff->startOfAttempt()->endOfAttempt();
                $backoff->currentLog(); // <<< calls currentLog() inside the loop
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () use ($newAfterFirstAttemptBackoff) {
            $backoff = $newAfterFirstAttemptBackoff();
            $backoff->reset();
            $attempts = 10;
            do {
                $backoff->startOfAttempt()->endOfAttempt();
                $backoff->currentLog(); // <<< calls currentLog() inside the loop
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];



        return $return;
    }

    /**
     * Test what the currentLog() method returns.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_what_current_log_returns(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm, null, 3);

        // not started yet
        $attemptLog1a = $backoff->currentLog(); // not available yet
        $backoff->startOfAttempt();
        $attemptLog1b = $backoff->currentLog();
        $backoff->endOfAttempt();
        $attemptLog1c = $backoff->currentLog(); // same as $attemptLog1b

        $backoff->step();
        $attemptLog2a = $backoff->currentLog(); // not available yet
        $backoff->startOfAttempt();
        $attemptLog2b = $backoff->currentLog();
        $backoff->endOfAttempt();
        $attemptLog2c = $backoff->currentLog(); // same as $attemptLog2b

        $backoff->step();
        $attemptLog3a = $backoff->currentLog(); // not available yet
        $backoff->startOfAttempt();
        $attemptLog3b = $backoff->currentLog();
        $backoff->endOfAttempt();
        $attemptLog3c = $backoff->currentLog(); // same as $attemptLog3b

        $backoff->step();
        $attemptLog4a = $backoff->currentLog(); // won't be available, has stopped

        self::assertNull($attemptLog1a);
        self::assertInstanceOf(AttemptLog::class, $attemptLog1b);
        self::assertSame($attemptLog1b, $attemptLog1c);

        self::assertNull($attemptLog2a);
        self::assertInstanceOf(AttemptLog::class, $attemptLog2b);
        self::assertSame($attemptLog2b, $attemptLog2c);

        self::assertNull($attemptLog3a);
        self::assertInstanceOf(AttemptLog::class, $attemptLog3b);
        self::assertSame($attemptLog3b, $attemptLog3c);

        self::assertNull($attemptLog4a);

        self::assertNotSame($attemptLog1b, $attemptLog2b);
        self::assertNotSame($attemptLog2a, $attemptLog3b);
    }

    /**
     * Test that no logs are generated when no attempts are allowed.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_no_log_is_generated_when_no_attempts_are_allowed(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        // logs are ok (maxAttempts null)
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null, // <<< no limit to the number of attempts
            null,
            null,
            true, // <<< run before the first attempt
        );

        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();

        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();

        self::assertSame(2, $backoff->currentAttemptNumber());
        self::assertNotNull($backoff->currentLog());
        self::assertCount(2, $backoff->logs());



        // logs are not ok (maxAttempts 0)
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            0, // <<< no attempts allowed
            null,
            null,
            true, // <<< run before the first attempt
        );

        $backoff->step();
        $caughtException = false;
        try {
            $backoff->startOfAttempt()->endOfAttempt();
        } catch (BackoffRuntimeException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);

        $backoff->step();
        $caughtException = false;
        try {
            $backoff->startOfAttempt()->endOfAttempt();
        } catch (BackoffRuntimeException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);

        self::assertNull($backoff->currentAttemptNumber());
        self::assertNull($backoff->currentLog());
        self::assertCount(0, $backoff->logs());
    }

    /**
     * Test that the ->endOfAttempt() method can't be called before the ->startOfAttempt() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public function test_that_end_of_attempt_cant_be_called_before_start_of_attempt(): void
    {
        $this->expectException(BackoffRuntimeException::class);

        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm);
        $backoff->endOfAttempt();
    }

    /**
     * Test that the ->endOfAttempt() method can't be called before the ->startOfAttempt() method.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public function test_that_end_of_attempt_cant_be_called_before_start_of_attempt_after_reset(): void
    {
        $this->expectException(BackoffRuntimeException::class);

        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm);

        $backoff->startOfAttempt()->endOfAttempt();
        $backoff->reset();
        $backoff->endOfAttempt();
    }

    /**
     * Test that the ->endOfAttempt() method won't calculate and overwrite the working time when called twice.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_end_of_attempt_wont_overwrite_the_calculated_working_time_when_called_twice(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm);

        $backoff->startOfAttempt();

        $backoff->endOfAttempt();
        $workingTime1 = $backoff->currentLog()?->workingTimeInUs();

        usleep(10);
        $backoff->endOfAttempt();
        $workingTime2 = $backoff->currentLog()?->workingTimeInUs();

        self::assertSame($workingTime1, $workingTime2);
    }

    /**
     * Test that the ->startOfAttempt() method will finalise the previous attempt log (if it exists).
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_start_of_attempt_will_finalise_the_previous_attempt_log(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm);

        $backoff->startOfAttempt();

        $attemptLog = $backoff->currentLog();

        self::assertInstanceOf(AttemptLog::class, $attemptLog);
        self::assertNull($attemptLog->workingTime()); // not finalised yet
        self::assertNull($attemptLog->overallWorkingTime()); // not finalised yet

        $backoff->step();
        $backoff->startOfAttempt();

        self::assertNotNull($attemptLog->workingTime());
        self::assertNotNull($attemptLog->overallWorkingTime());
    }

    /**
     * Test that resetting the BaseBackoffStrategy will reset the logs.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_reset_resets_the_logs(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            null,
            true, // <<< run before the first attempt
        );

        $backoff->step();
        self::assertCount(0, $backoff->logs()); // no attempts recorded yet
        $backoff->startOfAttempt();
        self::assertSame(1, $backoff->currentAttemptNumber());
        self::assertCount(1, $backoff->logs());

        $backoff->step();
        $backoff->startOfAttempt();
        self::assertSame(2, $backoff->currentAttemptNumber());
        self::assertCount(2, $backoff->logs());

        $backoff->reset();
        self::assertNull($backoff->currentAttemptNumber());
        self::assertCount(2, $backoff->logs()); // the logs aren't reset until looping starts again

        $backoff->step();
        self::assertCount(0, $backoff->logs()); // no attempts recorded yet
        $backoff->startOfAttempt();
        self::assertCount(1, $backoff->logs());

        $backoff->reset();
        $backoff->startOfAttempt();
        self::assertCount(1, $backoff->logs());

        $backoff->step();
        $backoff->startOfAttempt();
        $backoff->step();
        $backoff->startOfAttempt();
        self::assertSame(2, $backoff->currentAttemptNumber());
        self::assertCount(2, $backoff->logs());
    }

    /**
     * Test that the BaseBackoffStrategy populates the correct unit type.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_logged_unit_type(): void
    {
        $algorithm = new NoopBackoffAlgorithm();

        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            Settings::UNIT_SECONDS, // <<< seconds
        );
        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();
        self::assertSame(Settings::UNIT_SECONDS, $backoff->currentLog()?->unitType());

        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            Settings::UNIT_MILLISECONDS, // <<< milliseconds
        );
        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();
        self::assertSame(Settings::UNIT_MILLISECONDS, $backoff->currentLog()?->unitType());

        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            Settings::UNIT_MICROSECONDS, // <<< microseconds
        );
        $backoff->step();
        $backoff->startOfAttempt()->endOfAttempt();
        self::assertSame(Settings::UNIT_MICROSECONDS, $backoff->currentLog()?->unitType());
    }

    /**
     * Test that the BaseBackoffStrategy doesn't overwrite an existing AttemptLog for the same step.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_attempt_logs_arent_overwritten(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy(
            $algorithm,
            null,
            null,
            null,
            null,
            true, // <<< run before the first attempt
        );

        $backoff->startOfAttempt()->endOfAttempt();

        $attemptLog1 = $backoff->currentLog();
        $attemptLog2 = $backoff->currentLog();
        self::assertInstanceOf(AttemptLog::class, $attemptLog1);
        self::assertSame($attemptLog1, $attemptLog2);
    }

    /**
     * Test that no AttemptLogs are created when startOfAttempt() isn't called.
     *
     * When handling the loop yourself, ->startOfAttempt() and ->endOfAttempt() must be called to generate logs.
     *
     * This is because while ->step() could call ->startOfAttempt(), it doesn't know when the final attempt has ended.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_that_no_logs_are_created_when_start_of_attempt_isnt_called(): void
    {
        $algorithm = new NoopBackoffAlgorithm();
        $backoff = new BackoffStrategy($algorithm);

        $attemptLog1 = $backoff->currentLog();
        $backoff->step();
        $attemptLog2 = $backoff->currentLog();
        $backoff->step();
        $attemptLog3 = $backoff->currentLog();

        self::assertNull($attemptLog1);
        self::assertNull($attemptLog2);
        self::assertNull($attemptLog3);
        self::assertSame([], $backoff->logs());
    }
}
