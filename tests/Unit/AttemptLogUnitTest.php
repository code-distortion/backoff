<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use DateTime;

/**
 * Test the AttemptLog class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class AttemptLogUnitTest extends PHPUnitTestCase
{
    /**
     * Test that the AttemptLog accepts different values.
     *
     * @test
     * @dataProvider attemptLogDataProvider
     *
     * @param integer            $attemptNumber          The number of attempts that have been made.
     * @param integer|null       $maxPossibleAttempts    The total number attempts that could be made.
     * @param DateTime           $firstAttemptOccurredAt The time the first attempt occurred.
     * @param DateTime           $thisAttemptOccurredAt  The time this attempt occurred.
     * @param integer|float|null $delay                  The most recently used delay.
     * @param integer|float|null $workingTime            The time spent attempting the action.
     * @param integer|float|null $overallDelay           The overall delay (sum of all delays).
     * @param integer|float|null $overallWorkingTime     The overall time spent attempting the action.
     * @param string             $unitType               The unit type that delay and overall delay are in.
     * @return void
     */
    public static function test_backoff_attempt_dto(
        int $attemptNumber,
        ?int $maxPossibleAttempts,
        DateTime $firstAttemptOccurredAt,
        DateTime $thisAttemptOccurredAt,
        int|float|null $delay,
        int|float|null $workingTime,
        int|float|null $overallDelay,
        int|float|null $overallWorkingTime,
        string $unitType,
    ): void {

        $delayInSeconds = Support::convertTimespan($delay, $unitType, Settings::UNIT_SECONDS);
        $delayInMs = Support::convertTimespan($delay, $unitType, Settings::UNIT_MILLISECONDS);
        $delayInUs = Support::convertTimespan($delay, $unitType, Settings::UNIT_MICROSECONDS);

        $workingTimeInSeconds = Support::convertTimespan($workingTime, $unitType, Settings::UNIT_SECONDS);
        $workingTimeInMs = Support::convertTimespan($workingTime, $unitType, Settings::UNIT_MILLISECONDS);
        $workingTimeInUs = Support::convertTimespan($workingTime, $unitType, Settings::UNIT_MICROSECONDS);

        $overallDelayInSeconds = Support::convertTimespan($overallDelay, $unitType, Settings::UNIT_SECONDS);
        $overallDelayInMs = Support::convertTimespan($overallDelay, $unitType, Settings::UNIT_MILLISECONDS);
        $overallDelayInUs = Support::convertTimespan($overallDelay, $unitType, Settings::UNIT_MICROSECONDS);

        $overallWorkingTimeInSeconds = Support::convertTimespan(
            $overallWorkingTime,
            $unitType,
            Settings::UNIT_SECONDS
        );
        $overallWorkingTimeInMs = Support::convertTimespan(
            $overallWorkingTime,
            $unitType,
            Settings::UNIT_MILLISECONDS
        );
        $overallWorkingTimeInUs = Support::convertTimespan(
            $overallWorkingTime,
            $unitType,
            Settings::UNIT_MICROSECONDS
        );



        $dto = new AttemptLog(
            $attemptNumber,
            $maxPossibleAttempts,
            $firstAttemptOccurredAt,
            $thisAttemptOccurredAt,
            $delay,
            $workingTime,
            $overallDelay,
            $overallWorkingTime,
            $unitType,
        );



        self::assertSame($attemptNumber, $dto->attemptNumber());
        self::assertSame($maxPossibleAttempts, $dto->maxPossibleAttempts());
        self::assertSame($firstAttemptOccurredAt, $dto->firstAttemptOccurredAt());
        self::assertSame($thisAttemptOccurredAt, $dto->thisAttemptOccurredAt());



        self::assertSame($delay, $dto->delay());
        self::assertSame($delayInSeconds, $dto->delayInSeconds());
        self::assertSame($delayInMs, $dto->delayInMs());
        self::assertSame($delayInUs, $dto->delayInUs());



        // working time
        self::assertSame($workingTime, $dto->workingTime());
        self::assertSame($workingTimeInSeconds, $dto->workingTimeInSeconds());
        self::assertSame($workingTimeInMs, $dto->workingTimeInMs());
        self::assertSame($workingTimeInUs, $dto->workingTimeInUs());

        $dto->setWorkingTime(null);
        self::assertNull($dto->workingTime());
        self::assertNull($dto->workingTimeInSeconds());
        self::assertNull($dto->workingTimeInMs());
        self::assertNull($dto->workingTimeInUs());

        $dto->setWorkingTime($workingTime);
        self::assertSame($workingTime, $dto->workingTime());
        self::assertSame($workingTimeInSeconds, $dto->workingTimeInSeconds());
        self::assertSame($workingTimeInMs, $dto->workingTimeInMs());
        self::assertSame($workingTimeInUs, $dto->workingTimeInUs());



        self::assertSame($overallDelay, $dto->overallDelay());
        self::assertSame($overallDelayInSeconds, $dto->overallDelayInSeconds());
        self::assertSame($overallDelayInMs, $dto->overallDelayInMs());
        self::assertSame($overallDelayInUs, $dto->overallDelayInUs());



        self::assertSame($overallWorkingTime, $dto->overallWorkingTime());
        self::assertSame($overallWorkingTimeInSeconds, $dto->overallWorkingTimeInSeconds());
        self::assertSame($overallWorkingTimeInMs, $dto->overallWorkingTimeInMs());
        self::assertSame($overallWorkingTimeInUs, $dto->overallWorkingTimeInUs());

        $dto->setOverallWorkingTime(null);
        self::assertNull($dto->overallWorkingTime());
        self::assertNull($dto->overallWorkingTimeInSeconds());
        self::assertNull($dto->overallWorkingTimeInMs());
        self::assertNull($dto->overallWorkingTimeInUs());

        $dto->setOverallWorkingTime($overallWorkingTime);
        self::assertSame($overallWorkingTime, $dto->overallWorkingTime());
        self::assertSame($overallWorkingTimeInSeconds, $dto->overallWorkingTimeInSeconds());
        self::assertSame($overallWorkingTimeInMs, $dto->overallWorkingTimeInMs());
        self::assertSame($overallWorkingTimeInUs, $dto->overallWorkingTimeInUs());

        self::assertSame($unitType, $dto->unitType());
    }

    /**
     * DataProvider for test_backoff_attempt_dto.
     *
     * @return array
     */
    public static function attemptLogDataProvider(): array
    {
        $return = [];

        $default = [
            'attemptNumber' => 1,
            'maxPossibleAttempts' => null,
            'firstAttemptOccurredAt' => new DateTime('2024-01-01 00:00:00'),
            'thisAttemptOccurredAt' => new DateTime('2024-01-01 00:00:01'),
            'delay' => null,
            'workingTime' => null,
            'overallDelay' => null,
            'overallWorkingTime' => null,
            'unitType' => Settings::UNIT_SECONDS,
        ];



        $attemptNumbers = [1, 2];
        $maxPossibleAttempts = [null, 1, 10];
        $firstAttemptOccurredAts = [new DateTime('2024-01-01 00:00:00')];
        $thisAttemptOccurredAts = [new DateTime('2024-01-01 00:00:01')];
        $timespans = [
            Settings::UNIT_SECONDS => [null, 0, 0.5, 1, 2],
            Settings::UNIT_MILLISECONDS => [null, 0, 500, 1000, 2000],
            Settings::UNIT_MICROSECONDS => [null, 0, 500_000, 1000_000, 2000_000],
        ];



        foreach ($attemptNumbers as $attemptNumber) {
            $return[] = array_merge($default, ['attemptNumber' => $attemptNumber]);
        }

        foreach ($maxPossibleAttempts as $maxPossibleAttemptsValue) {
            $return[] = array_merge($default, ['$maxPossibleAttempts' => $maxPossibleAttemptsValue]);
        }

        foreach ($firstAttemptOccurredAts as $firstAttemptOccurredAt) {
            $return[] = array_merge($default, ['firstAttemptOccurredAt' => $firstAttemptOccurredAt]);
        }

        foreach ($thisAttemptOccurredAts as $thisAttemptOccurredAt) {
            $return[] = array_merge($default, ['thisAttemptOccurredAt' => $thisAttemptOccurredAt]);
        }

        foreach (array_keys($timespans) as $unitType) {
            foreach ($timespans[$unitType] as $timespan) {
                $return[] = array_merge($default, ['delay' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['workingTime' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['overallDelay' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['overallWorkingTime' => $timespan, 'unitType' => $unitType]);
            }
        }

        return $return;
    }

    /**
     * Test that the AttemptLog constructor throws an exception when given invalid data.
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    public function test_that_constructor_throws_exception(): void
    {
        $this->expectException(BackoffInitialisationException::class);

        new AttemptLog(
            1,
            null,
            new DateTime('2024-01-01 00:00:00'),
            new DateTime('2024-01-01 00:00:01'),
            null,
            null,
            null,
            null,
            'invalid-unit-type', // <<<
        );
    }
}
