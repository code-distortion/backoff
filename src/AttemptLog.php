<?php

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Support\Support;
use DateTime;

/**
 * A DTO of sorts, recording details about a backoff attempt.
 */
class AttemptLog
{
    /**
     * @param integer            $attemptNumber          The number of attempts that have been made.
     * @param integer|null       $maxPossibleAttempts    The total number attempts that could be made.
     * @param DateTime           $firstAttemptOccurredAt The time the first attempt occurred.
     * @param DateTime           $thisAttemptOccurredAt  The time this attempt occurred.
     * @param integer|float|null $delay                  The most recently used delay.
     * @param integer|float|null $workingTime            The time spent attempting the action.
     * @param integer|float|null $overallDelay           The overall delay (sum of all delays).
     * @param integer|float|null $overallWorkingTime     The overall time spent attempting the action.
     * @param string             $unitType               The unit type that delay and overall delay are in.
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public function __construct(
        private int $attemptNumber,
        private ?int $maxPossibleAttempts,
        private DateTime $firstAttemptOccurredAt,
        private DateTime $thisAttemptOccurredAt,
        private int|float|null $delay,
        private int|float|null $workingTime,
        private int|float|null $overallDelay,
        private int|float|null $overallWorkingTime,
        private string $unitType,
    ) {
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES)) {
            throw BackoffInitialisationException::invalidUnitType($this->unitType);
        }
    }



    /**
     * Get the number of attempts that have been made.
     *
     * @return integer
     */
    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Get the number attempts that could be made (null for infinite).
     *
     * Note: A BackoffStrategy can return false to end the attempts early. This won't be reflected here.
     *
     * @return integer|null
     */
    public function maxPossibleAttempts(): ?int
    {
        return $this->maxPossibleAttempts;
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
     * Get the time this attempt occurred.
     *
     * @return DateTime
     */
    public function thisAttemptOccurredAt(): DateTime
    {
        return $this->thisAttemptOccurredAt;
    }



    /**
     * Get the delay applied before this attempt, in the unit-of-measure specified.
     *
     * @return integer|float|null
     */
    public function delay(): int|float|null
    {
        return $this->delay;
    }

    /**
     * Get the delay applied before this attempt, in seconds.
     *
     * @return integer|float|null
     */
    public function delayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->delay, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the delay applied before this attempt, in milliseconds.
     *
     * @return integer|null
     */
    public function delayInMs(): ?int
    {
        return Support::convertTimespan($this->delay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the delay applied before this attempt, in microseconds.
     *
     * @return integer|null
     */
    public function delayInUs(): ?int
    {
        return Support::convertTimespan($this->delay, $this->unitType, Settings::UNIT_MICROSECONDS);
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
     * @return integer|null
     */
    public function workingTimeInMs(): ?int
    {
        return Support::convertTimespan($this->workingTime, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the time spent attempting the action, in microseconds (null for unknown).
     *
     * @return integer|null
     */
    public function workingTimeInUs(): ?int
    {
        return Support::convertTimespan($this->workingTime, $this->unitType, Settings::UNIT_MICROSECONDS);
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
     * @return integer|null
     */
    public function overallDelayInMs(): ?int
    {
        return Support::convertTimespan($this->overallDelay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the overall delay (sum of all delays), in microseconds.
     *
     * @return integer|null
     */
    public function overallDelayInUs(): ?int
    {
        return Support::convertTimespan($this->overallDelay, $this->unitType, Settings::UNIT_MICROSECONDS);
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
     * Set the overall time spent attempting the action (sum of all working time).
     *
     * @internal Used to set the working time once it's known.
     *
     * @param integer|float|null $overallWorkingTime The overall time spent attempting the action.
     * @return integer|float|null
     */
    public function setOverallWorkingTime(int|float|null $overallWorkingTime): int|float|null
    {
        return $this->overallWorkingTime = $overallWorkingTime;
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
     * @return integer|null
     */
    public function overallWorkingTimeInMs(): ?int
    {
        return Support::convertTimespan($this->overallWorkingTime, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the overall time spent attempting the action (sum of all working time), in microseconds (null for unknown).
     *
     * @return integer|null
     */
    public function overallWorkingTimeInUs(): ?int
    {
        return Support::convertTimespan($this->overallWorkingTime, $this->unitType, Settings::UNIT_MICROSECONDS);
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
