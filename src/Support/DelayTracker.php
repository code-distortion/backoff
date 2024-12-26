<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Support;

/**
 * Class that tracks delay info, for use while testing.
 */
class DelayTracker
{
    /** @var array<integer|float|null> The delays recorded for testing purposes. */
    private array $recordedDelays = [];

    /** @var array<integer|float|null> The delays recorded for testing purposes - in seconds. */
    private array $recordedDelaysInSeconds = [];

    /** @var array<integer|float|null> The delays recorded for testing purposes - in milliseconds. */
    private array $recordedDelaysInMs = [];

    /** @var array<integer|float|null> The delays recorded for testing purposes - in microseconds. */
    private array $recordedDelaysInUs = [];

    /** @var integer The number of times sleep() was called. */
    private int $sleepCallCount = 0;

    /** @var integer The number of times sleep() actually slept. */
    private int $actualTimesSlept = 0;



    /**
     * Record a delay, in the current unit-of-measure.
     *
     * @param integer|float|null $delay The delay to record.
     * @return void
     */
    public function recordDelay(int|float|null $delay): void
    {
        $this->recordedDelays[] = $delay;
    }

    /**
     * Get the delays recorded, in the current unit-of-measure.
     *
     * @return array<integer|float|null>
     */
    public function getDelays(): array
    {
        return $this->recordedDelays;
    }



    /**
     * Record a delay, in seconds.
     *
     * @param integer|float|null $delay The delay to record.
     * @return void
     */
    public function recordDelayInSeconds(int|float|null $delay): void
    {
        $this->recordedDelaysInSeconds[] = $delay;
    }

    /**
     * Get the delays recorded, in seconds.
     *
     * @return array<integer|float|null>
     */
    public function getDelaysInSeconds(): array
    {
        return $this->recordedDelaysInSeconds;
    }



    /**
     * Record a delay, in milliseconds.
     *
     * @param integer|float|null $delayMs The delay to record.
     * @return void
     */
    public function recordDelayInMs(int|float|null $delayMs): void
    {
        $this->recordedDelaysInMs[] = $delayMs;
    }

    /**
     * Get the delays recorded, in milliseconds.
     *
     * @return array<integer|float|null>
     */
    public function getDelaysInMs(): array
    {
        return $this->recordedDelaysInMs;
    }



    /**
     * Record a delay, in microseconds.
     *
     * @param integer|float|null $delayUS The delay to record.
     * @return void
     */
    public function recordDelayInUs(int|float|null $delayUS): void
    {
        $this->recordedDelaysInUs[] = $delayUS;
    }

    /**
     * Get the delays recorded, in microseconds.
     *
     * @return array<integer|float|null>
     */
    public function getDelaysInUs(): array
    {
        return $this->recordedDelaysInUs;
    }



    /**
     * Record that sleep() was called.
     *
     * @return void
     */
    public function recordSleepCall(): void
    {
        $this->sleepCallCount++;
    }

    /**
     * Get the number of times sleep() was called.
     *
     * @return integer
     */
    public function getSleepCallCount(): int
    {
        return $this->sleepCallCount;
    }



    /**
     * Record that sleep() actually slept.
     *
     * @return void
     */
    public function recordActualSleep(): void
    {
        $this->actualTimesSlept++;
    }

    /**
     * Get the number of times sleep() actually slept.
     *
     * @return integer
     */
    public function getActualTimesSlept(): int
    {
        return $this->actualTimesSlept;
    }
}
