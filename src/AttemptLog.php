<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Support\Support;
use DateTime;

/**
 * A DTO of sorts, recording details about a backoff attempt.
 */
final class AttemptLog
{
    /**
     * @param integer            $attemptNumber          The number of attempts that have been made.
     * @param integer|null       $maxAttempts            The total number attempts that could be made.
     * @param DateTime           $firstAttemptOccurredAt The time the first attempt occurred.
     * @param DateTime           $thisAttemptOccurredAt  The time this attempt occurred.
     * @param integer|float|null $workingTime            The time spent attempting the action.
     * @param integer|float|null $overallWorkingTime     The overall time spent attempting the action (including this
     *                                                   attempt).
     * @param integer|float|null $prevDelay              The delay that was used before this attempt.
     * @param integer|float|null $nextDelay              The delay that will occur next, before the next attempt.
     * @param integer|float|null $overallDelay           The overall delay (sum of all delays - excluding the next
     *                                                   delay).
     * @param string             $unitType               The unit type that delay and overall delay are in.
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public function __construct(
        private int $attemptNumber,
        private ?int $maxAttempts,
        private DateTime $firstAttemptOccurredAt,
        private DateTime $thisAttemptOccurredAt,
        private int|float|null $workingTime,
        private int|float|null $overallWorkingTime,
        private int|float|null $prevDelay,
        private int|float|null $nextDelay,
        private int|float|null $overallDelay,
        private string $unitType,
    ) {
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES, true)) {
            throw BackoffInitialisationException::invalidUnitType($this->unitType);
        }
    }





    /**
     * Get the attempt number this is for.
     *
     * @return integer
     */
    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Get the retry number this is for.
     *
     * @return integer
     */
    public function retryNumber(): int
    {
        return $this->attemptNumber - 1;
    }

    /**
     * Get the number attempts that could be made (null for infinite).
     *
     * Note: A BackoffAlgorithm can return false to end the attempts early. This won't be reflected here.
     *
     * @return integer|null
     */
    public function maxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    /**
     * Find out if the action will be retried or not.
     *
     * @return boolean
     */
    public function willRetry(): bool
    {
        return !is_null($this->nextDelay);
    }





    /**
     * Get the time the first attempt occurred.
     *
     * @return DateTime
     */
    public function firstAttemptOccurredAt(): DateTime
    {
        return $this->firstAttemptOccurredAt;
    }

    /**
     * Record when the attempts first occurred.
     *
     * @internal Used to set the first attempt time once it's known.
     *
     * @param DateTime $occurredAt The time the first attempt occurred.
     * @return void
     */
    public function setFirstAttemptOccurredAt(DateTime $occurredAt): void
    {
        $this->firstAttemptOccurredAt = $occurredAt;
    }



    /**
     * Get the time this attempt occurred.
     *
     * @return DateTime
     */
    public function thisAttemptOccurredAt(): DateTime
    {
        return $this->thisAttemptOccurredAt;
    }

    /**
     * Record when this attempt occurred at.
     *
     * @internal Used to set the time this attempt occurred at once it's known.
     *
     * @param DateTime $occurredAt The time this attempt occurred.
     * @return void
     */
    public function setThisAttemptOccurredAt(DateTime $occurredAt): void
    {
        $this->thisAttemptOccurredAt = $occurredAt;
    }





    /**
     * Get the time spent attempting the action, in the unit-of-measure specified (null for unknown).
     *
     * @return integer|float|null
     */
    public function workingTime(): int|float|null
    {
        return $this->workingTime;
    }

    /**
     * Set the time spent attempting the action.
     *
     * @internal Used to set the working time once it's known.
     *
     * @param integer|float|null $workingTime The time spent attempting the action.
     * @return void
     */
    public function setWorkingTime(int|float|null $workingTime): void
    {
        $this->workingTime = $workingTime;
    }

    /**
     * Get the time spent attempting the action, in seconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function workingTimeInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->workingTime, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the time spent attempting the action, in milliseconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function workingTimeInMs(): int|float|null
    {
        return Support::convertTimespan($this->workingTime, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the time spent attempting the action, in microseconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function workingTimeInUs(): int|float|null
    {
        return Support::convertTimespan($this->workingTime, $this->unitType, Settings::UNIT_MICROSECONDS);
    }



    /**
     * Get the overall time spent attempting the action (sum of all working time), in the unit-of-measure specified
     * (null for unknown).
     *
     * @return integer|float|null
     */
    public function overallWorkingTime(): int|float|null
    {
        return $this->overallWorkingTime;
    }

    /**
     * Get the overall time spent attempting the action (sum of all working time), in the unit-of-measure specified.
     *
     * @internal Used internally to remove some infection mutants.
     *
     * @return integer|float
     */
    public function overallWorkingTimeAsNumber(): int|float
    {
        return $this->overallWorkingTime() ?? 0;
    }

    /**
     * Set the overall time spent attempting the action (sum of all working time).
     *
     * @internal Used to set the working time once it's known.
     *
     * @param integer|float|null $overallWorkingTime The overall time spent attempting the action.
     * @return void
     */
    public function setOverallWorkingTime(int|float|null $overallWorkingTime): void
    {
        $this->overallWorkingTime = $overallWorkingTime;
    }

    /**
     * Get the overall time spent attempting the action (sum of all working time), in seconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function overallWorkingTimeInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->overallWorkingTime, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the overall time spent attempting the action (sum of all working time), in milliseconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function overallWorkingTimeInMs(): int|float|null
    {
        return Support::convertTimespan($this->overallWorkingTime, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the overall time spent attempting the action (sum of all working time), in microseconds (null for unknown).
     *
     * @return integer|float|null
     */
    public function overallWorkingTimeInUs(): int|float|null
    {
        return Support::convertTimespan($this->overallWorkingTime, $this->unitType, Settings::UNIT_MICROSECONDS);
    }





    /**
     * Get the delay that was applied before this attempt, in the unit-of-measure specified.
     *
     * @return integer|float|null
     */
    public function prevDelay(): int|float|null
    {
        return $this->prevDelay;
    }

    /**
     * Get the delay that was applied before this attempt, in seconds.
     *
     * @return integer|float|null
     */
    public function prevDelayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->prevDelay, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the delay that was applied before this attempt, in milliseconds.
     *
     * @return integer|float|null
     */
    public function prevDelayInMs(): int|float|null
    {
        return Support::convertTimespan($this->prevDelay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the delay that was applied before this attempt, in microseconds.
     *
     * @return integer|float|null
     */
    public function prevDelayInUs(): int|float|null
    {
        return Support::convertTimespan($this->prevDelay, $this->unitType, Settings::UNIT_MICROSECONDS);
    }



    /**
     * Get the delay that will be used before the next attempt, in the unit-of-measure specified.
     *
     * @return integer|float|null
     */
    public function nextDelay(): int|float|null
    {
        return $this->nextDelay;
    }

    /**
     * Get the delay that will be used before the next attempt, in seconds.
     *
     * @return integer|float|null
     */
    public function nextDelayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the delay that will be used before the next attempt, in milliseconds.
     *
     * @return integer|float|null
     */
    public function nextDelayInMs(): int|float|null
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the delay that will be used before the next attempt, in microseconds.
     *
     * @return integer|float|null
     */
    public function nextDelayInUs(): int|float|null
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_MICROSECONDS);
    }



    /**
     * Get the overall delay (sum of all delays), in the unit-of-measure specified.
     *
     * @return integer|float|null
     */
    public function overallDelay(): int|float|null
    {
        return $this->overallDelay;
    }

    /**
     * Get the overall delay (sum of all delays), in seconds.
     *
     * @return integer|float|null
     */
    public function overallDelayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->overallDelay, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the overall delay (sum of all delays), in milliseconds.
     *
     * @return integer|float|null
     */
    public function overallDelayInMs(): int|float|null
    {
        return Support::convertTimespan($this->overallDelay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the overall delay (sum of all delays), in microseconds.
     *
     * @return integer|float|null
     */
    public function overallDelayInUs(): int|float|null
    {
        return Support::convertTimespan($this->overallDelay, $this->unitType, Settings::UNIT_MICROSECONDS);
    }



    /**
     * Get the unit type that delay and overall delay are in.
     *
     * @return string
     */
    public function unitType(): string
    {
        return $this->unitType;
    }
}
