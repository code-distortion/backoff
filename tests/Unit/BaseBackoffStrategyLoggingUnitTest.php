<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\BackoffStrategy;
use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use DateTime;

/**
 * Test how the AbstractBackoffHandler logs the attempts.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BaseBackoffHandlerLoggingUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the AbstractBackoffHandler generates AttemptLog log objects properly.
     *
     * @test
     * @dataProvider logGeneratorDataProvider
     *
     * @param callable $generateLogs A callback to run through a loop and generate logs.
     * @return void
     */
    public static function test_backoff_generates_logs_properly(callable $generateLogs): void
    {
        $start = new DateTime();
        $logs = $generateLogs();



        self::assertCount(3, $logs);
        self::assertSame([0, 1, 2], array_keys($logs));



        // log 1

        self::assertInstanceOf(AttemptLog::class, $logs[0]);

        self::assertSame(1, $logs[0]->attemptNumber());
        self::assertSame(3, $logs[0]->maxPossibleAttempts());

        $firstAttemptOccurredAt1 = $logs[0]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt1 = $logs[0]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[0]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[0]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual(0.01, Support::timeDiff($start, $thisAttemptOccurredAt1)); // happened recently
        self::assertSame(0.0, Support::timeDiff($firstAttemptOccurredAt1, $thisAttemptOccurredAt1));

        self::assertNull($logs[0]->delay());
        self::assertLessThan(1000, $logs[0]->workingTime());
        self::assertNull($logs[0]->overallDelay());
        self::assertLessThan(1000, $logs[0]->overallWorkingTime());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[0]->unitType());



        // log 2

        self::assertInstanceOf(AttemptLog::class, $logs[1]);

        self::assertSame(2, $logs[1]->attemptNumber());
        self::assertSame(3, $logs[1]->maxPossibleAttempts());

        $firstAttemptOccurredAt2 = $logs[1]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt2 = $logs[1]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[1]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[1]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual(0.01, Support::timeDiff($start, $thisAttemptOccurredAt2)); // happened recently
        self::assertGreaterThan(0, Support::timeDiff($firstAttemptOccurredAt2, $thisAttemptOccurredAt2));
        self::assertLessThanOrEqual(0.01, Support::timeDiff($firstAttemptOccurredAt2, $thisAttemptOccurredAt2));

        self::assertSame(1000, $logs[1]->delay());
        self::assertLessThan(1000, $logs[1]->workingTime());
        self::assertSame(1000, $logs[1]->overallDelay());
        self::assertLessThan(1000, $logs[1]->overallWorkingTime());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[1]->unitType());

        // overall working time should be the sum of the individual working times
        self::assertSame($logs[1]->overallWorkingTime(), $logs[0]->workingTime() + $logs[1]->workingTime());



        // log 3

        self::assertInstanceOf(AttemptLog::class, $logs[2]);

        self::assertSame(3, $logs[2]->attemptNumber());
        self::assertSame(3, $logs[2]->maxPossibleAttempts());

        $firstAttemptOccurredAt3 = $logs[2]->firstAttemptOccurredAt();
        $thisAttemptOccurredAt3 = $logs[2]->thisAttemptOccurredAt();
        self::assertInstanceOf(DateTime::class, $logs[2]->firstAttemptOccurredAt());
        self::assertInstanceOf(DateTime::class, $logs[2]->thisAttemptOccurredAt());
        self::assertLessThanOrEqual(0.01, Support::timeDiff($start, $thisAttemptOccurredAt3)); // happened recently
        self::assertGreaterThan(0, Support::timeDiff($firstAttemptOccurredAt3, $thisAttemptOccurredAt3));
        self::assertLessThanOrEqual(0.01, Support::timeDiff($firstAttemptOccurredAt3, $thisAttemptOccurredAt3));

        self::assertSame(2000, $logs[2]->delay());
        self::assertNull($logs[2]->workingTime());
        self::assertSame(3000, $logs[2]->overallDelay());
        self::assertNull($logs[2]->overallWorkingTime());
        self::assertSame(Settings::UNIT_MICROSECONDS, $logs[2]->unitType());

        // overall delay time should be the sum of the individual delay times
        self::assertSame($logs[2]->overallDelay(), $logs[1]->delay() + $logs[2]->delay());
    }

    /**
     * DataProvider for test_backoff_generates_logs_properly.
     *
     * @return array<array<callable>>
     */
    public static function logGeneratorDataProvider(): array
    {
        $return = [];


        // $backoff->step() called at the end of the loop
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3);
            $attempts = 10;
            do {
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3);
            $backoff->reset();
            $attempts = 10;
            do {
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3);
            $attempts = 10;
            do {
                $latestLog = $backoff->latestLog();
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3);
            $backoff->reset();
            $attempts = 10;
            do {
                $latestLog = $backoff->latestLog();
            } while (($attempts-- > 0) && ($backoff->step()));
            return $backoff->logs();
        };
        $return[] = [$callback];


        // $backoff->step() called at the beginning of the loop
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3)->runsBeforeFirstAttempt();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $success = false;
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3)->runsBeforeFirstAttempt();
            $backoff->reset();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $success = false;
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3)->runsBeforeFirstAttempt();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $latestLog = $backoff->latestLog();
                $success = false;
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        //
        $callback = function () {
            $backoff = BackoffStrategy::linear(1000)->unitUs()->maxAttempts(3)->runsBeforeFirstAttempt();
            $backoff->reset();
            $attempts = 10;
            while (($attempts-- > 0) && ($backoff->step())) {
                $latestLog = $backoff->latestLog();
                $success = false;
            }
            return $backoff->logs();
        };
        $return[] = [$callback];

        return $return;
    }

    /**
     * Test that no logs are generated when no attempts are allowed.
     *
     * @return void
     */
    public function test_that_no_log_is_generated_when_no_attempts_are_allowed(): void
    {
        // logs are ok (maxAttempts 3)
        $backoff = BackoffStrategy::linear(1)->unitMs()->maxAttempts(3)->runsBeforeFirstAttempt();
        $backoff->step();
        $backoff->step();
        self::assertSame(2, $backoff->getAttemptNumber());
        self::assertNotNull($backoff->latestLog());
        self::assertCount(2, $backoff->logs());

        // logs are not ok (maxAttempts 0)
        $backoff = BackoffStrategy::linear(1)->unitMs()->maxAttempts(0)->runsBeforeFirstAttempt();
        $backoff->step();
        $backoff->step();
        self::assertSame(1, $backoff->getAttemptNumber());
        self::assertNull($backoff->latestLog());
        self::assertCount(0, $backoff->logs());
    }

    /**
     * Test that the AbstractBackoffHandler can be reset.
     *
     * @return void
     */
    public static function test_that_reset_resets_the_logs(): void
    {
        $backoff = BackoffStrategy::linear(1)->unitMs()->maxAttempts(3)->runsBeforeFirstAttempt();

        $backoff->step();
        $backoff->step();
        self::assertSame(2, $backoff->getAttemptNumber());
        self::assertCount(2, $backoff->logs());

        $backoff->reset();
        self::assertSame(1, $backoff->getAttemptNumber());
        self::assertCount(0, $backoff->logs());

        $backoff->step();
        $backoff->step();
        self::assertSame(2, $backoff->getAttemptNumber());
        self::assertCount(2, $backoff->logs());
    }

    /**
     * Test that the AbstractBackoffHandler populates the correct unit type.
     *
     * @return void
     */
    public static function test_the_logged_unit_type(): void
    {
        $backoff = BackoffStrategy::linear(1)->unitSeconds()->maxAttempts(3)->runsBeforeFirstAttempt();
        $backoff->step();
        self::assertSame(Settings::UNIT_SECONDS, $backoff->latestLog()->unitType());

        $backoff = BackoffStrategy::linear(1)->unitMs()->maxAttempts(3)->runsBeforeFirstAttempt();
        $backoff->step();
        self::assertSame(Settings::UNIT_MILLISECONDS, $backoff->latestLog()->unitType());

        $backoff = BackoffStrategy::linear(1)->unitUs()->maxAttempts(3)->runsBeforeFirstAttempt();
        $backoff->step();
        self::assertSame(Settings::UNIT_MICROSECONDS, $backoff->latestLog()->unitType());
    }
}
