<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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
     * @param integer|null       $maxAttempts            The total number attempts that could be made.
     * @param DateTime           $firstAttemptOccurredAt The time the first attempt occurred.
     * @param DateTime           $thisAttemptOccurredAt  The time this attempt occurred.
     * @param integer|float|null $workingTime            The time spent attempting the action.
     * @param integer|float|null $overallWorkingTime     The overall time spent attempting the action.
     * @param integer|float|null $prevDelay              The delay that was used before this attempt.
     * @param integer|float|null $nextDelay              The delay that will occur next, before the next attempt.
     * @param integer|float|null $overallDelay           The overall delay (sum of all delays - excluding the next
     *                                                   delay).
     * @param string             $unitType               The unit type that delay and overall delay are in.
     * @return void
     */
    #[Test]
    #[DataProvider('attemptLogDataProvider')]
    public static function test_backoff_attempt_dto_initialisation_and_retrieving_values(
        int $attemptNumber,
        ?int $maxAttempts,
        DateTime $firstAttemptOccurredAt,
        DateTime $thisAttemptOccurredAt,
        int|float|null $workingTime,
        int|float|null $overallWorkingTime,
        int|float|null $prevDelay,
        int|float|null $nextDelay,
        int|float|null $overallDelay,
        string $unitType,
    ): void {

        $prevDelayInSeconds = Support::convertTimespan($prevDelay, $unitType, Settings::UNIT_SECONDS);
        $prevDelayInMs = Support::convertTimespan($prevDelay, $unitType, Settings::UNIT_MILLISECONDS);
        $prevDelayInUs = Support::convertTimespan($prevDelay, $unitType, Settings::UNIT_MICROSECONDS);

        $nextDelayInSeconds = Support::convertTimespan($nextDelay, $unitType, Settings::UNIT_SECONDS);
        $nextDelayInMs = Support::convertTimespan($nextDelay, $unitType, Settings::UNIT_MILLISECONDS);
        $nextDelayInUs = Support::convertTimespan($nextDelay, $unitType, Settings::UNIT_MICROSECONDS);

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
            $maxAttempts,
            $firstAttemptOccurredAt,
            $thisAttemptOccurredAt,
            $workingTime,
            $overallWorkingTime,
            $prevDelay,
            $nextDelay,
            $overallDelay,
            $unitType,
        );



        // attempt counts
        self::assertSame($attemptNumber, $dto->attemptNumber());
        self::assertSame($attemptNumber - 1, $dto->retryNumber());
        self::assertSame($maxAttempts, $dto->maxAttempts());



        // first attempt occurred at
        self::assertSame($firstAttemptOccurredAt, $dto->firstAttemptOccurredAt());
        $occurredAt = new DateTime();
        $dto->setFirstAttemptOccurredAt($occurredAt);
        self::assertSame($occurredAt, $dto->firstAttemptOccurredAt());

        // this attempt occurred at
        self::assertSame($thisAttemptOccurredAt, $dto->thisAttemptOccurredAt());
        $occurredAt = new DateTime();
        $dto->setThisAttemptOccurredAt($occurredAt);
        self::assertSame($occurredAt, $dto->thisAttemptOccurredAt());



        // working time
        self::assertSame($workingTime, $dto->workingTime());
        self::assertSame($workingTimeInSeconds, $dto->workingTimeInSeconds());
        self::assertSame($workingTimeInMs, $dto->workingTimeInMs());
        self::assertSame($workingTimeInUs, $dto->workingTimeInUs());
        // set to null
        $dto->setWorkingTime(null);
        self::assertNull($dto->workingTime());
        self::assertNull($dto->workingTimeInSeconds());
        self::assertNull($dto->workingTimeInMs());
        self::assertNull($dto->workingTimeInUs());
        // set back again
        $dto->setWorkingTime($workingTime);
        self::assertSame($workingTime, $dto->workingTime());
        self::assertSame($workingTimeInSeconds, $dto->workingTimeInSeconds());
        self::assertSame($workingTimeInMs, $dto->workingTimeInMs());
        self::assertSame($workingTimeInUs, $dto->workingTimeInUs());



        // overall working time
        self::assertSame($overallWorkingTime, $dto->overallWorkingTime());
        self::assertSame($overallWorkingTime ?? 0, $dto->overallWorkingTimeAsNumber());
        self::assertSame($overallWorkingTimeInSeconds, $dto->overallWorkingTimeInSeconds());
        self::assertSame($overallWorkingTimeInMs, $dto->overallWorkingTimeInMs());
        self::assertSame($overallWorkingTimeInUs, $dto->overallWorkingTimeInUs());
        // set to null
        $dto->setOverallWorkingTime(null);
        self::assertNull($dto->overallWorkingTime());
        self::assertSame(0, $dto->overallWorkingTimeAsNumber());
        self::assertNull($dto->overallWorkingTimeInSeconds());
        self::assertNull($dto->overallWorkingTimeInMs());
        self::assertNull($dto->overallWorkingTimeInUs());
        // set back again
        $dto->setOverallWorkingTime($overallWorkingTime);
        self::assertSame($overallWorkingTime, $dto->overallWorkingTime());
        self::assertSame($overallWorkingTime ?? 0, $dto->overallWorkingTimeAsNumber());
        self::assertSame($overallWorkingTimeInSeconds, $dto->overallWorkingTimeInSeconds());
        self::assertSame($overallWorkingTimeInMs, $dto->overallWorkingTimeInMs());
        self::assertSame($overallWorkingTimeInUs, $dto->overallWorkingTimeInUs());



        // prev delay
        self::assertSame($prevDelay, $dto->prevDelay());
        self::assertSame($prevDelayInSeconds, $dto->prevDelayInSeconds());
        self::assertSame($prevDelayInMs, $dto->prevDelayInMs());
        self::assertSame($prevDelayInUs, $dto->prevDelayInUs());

        // next delay
        self::assertSame($nextDelay, $dto->nextDelay());
        self::assertSame($nextDelayInSeconds, $dto->nextDelayInSeconds());
        self::assertSame($nextDelayInMs, $dto->nextDelayInMs());
        self::assertSame($nextDelayInUs, $dto->nextDelayInUs());
        self::assertSame(!is_null($nextDelay), $dto->willRetry());

        // overall delay
        self::assertSame($overallDelay, $dto->overallDelay());
        self::assertSame($overallDelayInSeconds, $dto->overallDelayInSeconds());
        self::assertSame($overallDelayInMs, $dto->overallDelayInMs());
        self::assertSame($overallDelayInUs, $dto->overallDelayInUs());



        self::assertSame($unitType, $dto->unitType());
    }

    /**
     * DataProvider for test_backoff_attempt_dto.
     *
     * @return array<array<string,integer|float|null|DateTime|string>>
     */
    public static function attemptLogDataProvider(): array
    {
        $return = [];

        $default = [
            'attemptNumber' => 1,
            'maxAttempts' => null,
            'firstAttemptOccurredAt' => new DateTime('2024-01-01 00:00:00'),
            'thisAttemptOccurredAt' => new DateTime('2024-01-01 00:00:01'),
            'workingTime' => null,
            'overallWorkingTime' => null,
            'prevDelay' => null,
            'nextDelay' => null,
            'overallDelay' => null,
            'unitType' => Settings::UNIT_SECONDS,
        ];



        $attemptNumbers = [1, 2];
        $maxAttempts = [null, 1, 10];
        $firstAttemptOccurredAts = [new DateTime('2024-01-01 00:00:00')];
        $thisAttemptOccurredAts = [new DateTime('2024-01-01 00:00:01')];
        $timespans = [
            Settings::UNIT_SECONDS => [null, 0, 0.5, 1, 2],
            Settings::UNIT_MILLISECONDS => [null, 0, 500.0, 1000.0, 2000.0],
            Settings::UNIT_MICROSECONDS => [null, 0, 500_000.0, 1_000_000.0, 2_000_000.0],
        ];



        foreach ($attemptNumbers as $attemptNumber) {
            $return[] = array_merge($default, ['attemptNumber' => $attemptNumber]);
        }

        foreach ($maxAttempts as $maxAttemptsValue) {
            $return[] = array_merge($default, ['maxAttempts' => $maxAttemptsValue]);
        }

        foreach ($firstAttemptOccurredAts as $firstAttemptOccurredAt) {
            $return[] = array_merge($default, ['firstAttemptOccurredAt' => $firstAttemptOccurredAt]);
        }

        foreach ($thisAttemptOccurredAts as $thisAttemptOccurredAt) {
            $return[] = array_merge($default, ['thisAttemptOccurredAt' => $thisAttemptOccurredAt]);
        }

        foreach (array_keys($timespans) as $unitType) {
            foreach ($timespans[$unitType] as $timespan) {
                $return[] = array_merge($default, ['workingTime' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['overallWorkingTime' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['prevDelay' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['nextDelay' => $timespan, 'unitType' => $unitType]);
                $return[] = array_merge($default, ['overallDelay' => $timespan, 'unitType' => $unitType]);
            }
        }

        return $return;
    }

    /**
     * Test that the AttemptLog constructor throws an exception when given invalid data.
     *
     * @test
     *
     * @return void
     * @throws BackoffInitialisationException This will always be thrown.
     */
    #[Test]
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
            null,
            'invalid-unit-type', // <<<
        );
    }
}
