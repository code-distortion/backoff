<?php

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Settings;
use DateTime;

/**
 * Class that implements the backoff strategy logic.
 */
abstract class BaseBackoffHandler implements BackoffHandlerInterface
{
    // settings - the rest are in the constructor

    /** @var string The unit type to use (from Settings::UNIT_XXX, default: seconds). */
    protected string $unitType;



    // working variables

    /** @var boolean Whether the backoff handler should stop. */
    private bool $stopped;

    /** @var integer|null The retry attempt count. */
    private ?int $attemptNumber;

    /** @var integer|null An hrtime(true) point in time to use as an anchor point to sleep from. To improve accuracy. */
    private ?int $sleepStart;

//    /** @var integer|float|null The base delay to use. */
//    private int|float|null $nextBaseDelay;

    /** @var integer|float|null The delay to use, after jitter was applied. */
    private int|float|null $nextDelay;

//    /** @var integer|float|null The base delay that was used last time. */
//    private int|float|null $prevBaseDelay;

    /** @var integer|float|null The delay that was used last time, after jitter was applied. */
    private int|float|null $prevDelay;

    /** @var integer|float|null The overall delay (sum of all delays). */
    private int|float|null $overallDelay;

    /** @var DateTime The time the first attempt occurred - in case $runsBeforeFirstAttempt is false. */
    private Datetime $firstAttemptOccurredAt;

    /** @var array<integer,AttemptLog> The history of attempts. */
    protected array $attemptLogs;



    // values used for testing

    /** @var boolean Whether to record the delays for testing purposes. */
    private bool $recordForTest = false;

    /** @var array<integer|float|null> The delays recorded for testing purposes. */
    private array $recordedDelays = [];

    /** @var array<integer|float|null> The delays recorded for testing purposes - in seconds. */
    private array $recordedDelaysInSeconds = [];

    /** @var array<integer|null> The delays recorded for testing purposes - in milliseconds. */
    private array $recordedDelaysInMs = [];

    /** @var array<integer|null> The delays recorded for testing purposes - in microseconds. */
    private array $recordedDelaysInUs = [];

    /** @var integer The number of times sleep() was called. */
    private int $sleepCallCount = 0;

    /** @var integer The number of times sleep() actually slept. */
    private int $actualTimesSlept = 0;



    /**
     * Constructor
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm       The backoff algorithm to use.
     * @param JitterInterface|null      $jitter                 The jitter to apply (default: no jitter).
     * @param integer|null              $maxAttempts            The maximum number of attempts to allow - null for
     *                                                          infinite (default: null).
     * @param integer|float|null        $maxDelay               The maximum delay to allow (optional).
     * @param string|null               $unitType               The unit type to use
     *                                                          (from Settings::UNIT_XXX, default: seconds).
     * @param boolean                   $runsBeforeFirstAttempt Whether the backoff handler should start with the first
     *                                                          attempt, meaning no initial delay.
     * @param boolean                   $immediateFirstRetry    Whether to insert a 0 delay as the first retry delay.
     * @param boolean                   $delaysEnabled          Whether delays are allowed or not.
     * @param boolean                   $retriesEnabled         Whether retries are allowed or not.
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public function __construct(
        protected BackoffAlgorithmInterface $backoffAlgorithm,
        protected ?JitterInterface $jitter = null,
        protected ?int $maxAttempts = null,
        protected int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        protected bool $runsBeforeFirstAttempt = false,
        protected bool $immediateFirstRetry = false,
        protected bool $delaysEnabled = true,
        protected bool $retriesEnabled = true,
    ) {

        $this->unitType = $unitType ?? Settings::UNIT_SECONDS;
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES)) {
            throw BackoffInitialisationException::invalidUnitType($this->unitType);
        }

        $this->reset();
    }



    /**
     * Reset the backoff handler to its initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        // working variables

        // stop straight away if no attempts are allowed
        $this->reassessInitialStoppedState();
        $this->attemptNumber = null;
        $this->sleepStart = null;
//        $this->nextBaseDelay = null;
        $this->nextDelay = null;
//        $this->prevBaseDelay = null;
        $this->prevDelay = null;
        $this->overallDelay = null;
        $this->firstAttemptOccurredAt = new DateTime();
        $this->attemptLogs = [];

        // values used for testing
        $this->recordForTest = false;
        $this->recordedDelays = [];
        $this->recordedDelaysInSeconds = [];
        $this->recordedDelaysInMs = [];
        $this->recordedDelaysInUs = [];
        $this->sleepCallCount = 0;
        $this->actualTimesSlept = 0;

        return $this;
    }

    /**
     * Reassess if the backoff handler should be stopped because of the max attempts.
     *
     * @return void
     */
    protected function reassessInitialStoppedState(): void
    {
        $this->stopped = (!is_null($this->maxAttempts)) && ($this->maxAttempts <= 0);
    }





    /**
     * Run a step of the backoff process.
     *
     * @return boolean
     */
    public function step(): bool
    {
        // set $this->sleepStart here for increased accuracy
        $this->sleepStart = hrtime(true);

        $this->performBackoffLogic();

        return $this->sleep();
    }





    /**
     * Calculate the delay needed before retrying an action next.
     *
     * @return boolean
     */
    public function performBackoffLogic(): bool
    {
        // don't continue if we've already stopped
        if ($this->stopped) {
            return false;
        }

        // when this is the first attempt…
        if (is_null($this->attemptNumber)) {
            // when $backoff->step() is called at the BEGINNING of the loop…
            if ($this->runsBeforeFirstAttempt) {
                // update the $firstAttemptOccurredAt to be now
                $this->firstAttemptOccurredAt = new DateTime();
            }
            $this->storeAttemptLog();
        }

        // handle the first call - don't use a delay if this is the actual first attempt
        if ($this->isFirstAttempt()) {
            $this->attemptNumber = 1;
            return true;
        }

//        $this->prevBaseDelay = $this->nextBaseDelay;
        $this->prevDelay = $this->nextDelay;

//        $this->nextBaseDelay = null;
        $this->nextDelay = null;

        /** @infection-ignore-all $this->attemptNumber = 1; */
        $this->attemptNumber ??= 1;
        $this->attemptNumber++;

        if ($this->tooManyAttempts($this->attemptNumber)) {
            $this->stopped = true;
            return false;
        }

        $delay = $this->calculateDelay();

        // check if the backoff algorithm chose to stop
        if (is_null($delay)) {
            $this->stopped = true;
            return false;
        }

        $this->useDelay($delay);

        return true;
    }

    /**
     * Check if this is the first attempt.
     *
     * @return boolean
     */
    private function isFirstAttempt(): bool
    {
        if (!is_null($this->attemptNumber)) {
            return false;
        }

        return $this->runsBeforeFirstAttempt;
    }

    /**
     * Determine if the number of attempts has been exceeded.
     *
     * @param integer $attemptNumber The retry being attempted.
     * @return boolean
     */
    private function tooManyAttempts(int $attemptNumber): bool
    {
        if (!$this->retriesEnabled) {
            return true;
        }

        if ($this->maxAttempts === null) {
            return false;
        }

        if ($attemptNumber <= $this->maxAttempts) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the delay needed before the next retry.
     *
     * This takes into account that a delay of 0 might be inserted as the first retry delay
     *
     * @return integer|float|null
     */
    private function calculateDelay(): int|float|null
    {
        $retryNumber = $this->attemptNumber - 1;

        if ($this->immediateFirstRetry) {

            /** @infection-ignore-all return xx ? -1 : xx; */
            return ($retryNumber == 1)
                ? 0
                : $this->backoffAlgorithm->calculateBaseDelay($retryNumber - 1, $this->prevDelay);
        }

        return $this->backoffAlgorithm->calculateBaseDelay($retryNumber, $this->prevDelay);
    }

    /**
     * Use the delay that was calculated, applying jitter if desired, and only when delays are enabled.
     *
     * @param integer|float $delay The delay to use.
     * @return void
     */
    private function useDelay(int|float $delay): void
    {
        if (!$this->delaysEnabled) {
//            $this->nextBaseDelay = 0;
            $this->nextDelay = 0;
            return;
        }

        $delay = $this->enforceBounds($delay);
//        $this->nextBaseDelay = $delay;
        $this->nextDelay = $this->backoffAlgorithm->jitterMayBeApplied()
            ? $this->applyJitter($delay)
            : $delay;
    }

    /**
     * Apply bounds to the delay so the delay isn't below 0, or above the maxDelay.
     *
     * @param integer|float $delay The delay to apply bounds to.
     * @return integer|float
     */
    private function enforceBounds(int|float $delay): int|float
    {
        if (!is_null($this->maxDelay)) {
            $delay = min($delay, $this->maxDelay);
        }

        return max(0, $delay);
    }

    /**
     * Apply jitter to the delay, if desired.
     *
     * @param integer|float $delay The delay to apply jitter to.
     * @return integer|float
     */
    private function applyJitter(int|float $delay): int|float
    {
        if (!$this->jitter) {
            return $delay;
        }

        return $this->jitter->apply($delay);
    }





    /**
     * Check if the backoff handler has started yet.
     *
     * @return boolean
     */
    protected function hasStarted(): bool
    {
        return count($this->attemptLogs) > 0;
    }

    /**
     * Check if the backoff handler should stop.
     *
     * @return boolean
     */
    public function shouldStop(): bool
    {
        return $this->stopped;
    }

    /**
     * Sleep for the calculated period.
     *
     * @return boolean
     */
    public function sleep(): bool
    {
        // use the previously set $this->sleepStart for increased accuracy, if available
        $start = !is_null($this->sleepStart)
            ? $this->sleepStart
            : hrtime(true);
        $this->sleepStart = null;



        if ($this->recordForTest) {
            $this->sleepCallCount++;
        }

        if ($this->stopped) {
            return false;
        }



        if ($this->recordForTest) {
            $this->recordedDelays[] = $this->getDelay();
            $this->recordedDelaysInSeconds[] = $this->getDelayInSeconds();
            $this->recordedDelaysInMs[] = $this->getDelayInMs();
            $this->recordedDelaysInUs[] = $this->getDelayInUs();
        }

        $microsecondDelay = $this->getDelayInUs();
        if (is_null($microsecondDelay)) {
            return true; // no delay calculated yet
        }

        $this->overallDelay ??= 0;
        $this->overallDelay += $this->nextDelay;

        $this->performSleep($start, $microsecondDelay);
        $this->storeAttemptLog();

        return true;
    }

    /**
     * Sleep until a specific time, in nanoseconds.
     *
     * @infection-ignore-all (so many things).
     *
     * @param integer $start        The hrtime(true) start time.
     * @param integer $microseconds The number of microseconds to sleep for.
     * @return void
     */
    private function performSleep(int $start, int $microseconds): void
    {
        if ($this->recordForTest) {
            $this->actualTimesSlept++;
            return;
        }

        $until = $start + ($microseconds * 1000);

        do {

            // re/calculate the remaining time each iteration.
            // this is to take overhead into account and avoid cumulative errors
            // (PHP's handling of the signal, other system-related delays, etc.)
            // when time_nanosleep() is interrupted by a signal and returns early

            $remaining = $until - hrtime(true);
            if ($remaining <= 0) {
                break;
            }

            $remainingSeconds = intdiv($remaining, 1_000_000_000); // pick the whole seconds
            $remainingNanoseconds = $remaining % 1_000_000_000; // pick the remaining nanoseconds

            $result = time_nanosleep($remainingSeconds, $remainingNanoseconds);

            // the sleep completed without interruption
            if ($result === true) {
                break;
            }

            // the sleep was interrupted, continue the loop, which recalculate the remaining time
            if (is_array($result)) {
                continue;
            }

            // an error occurred
            return;

        } while (true);
    }



    /**
     * Retrieve the latest AttemptLog, representing the most current attempt.
     *
     * @return AttemptLog|null
     */
    public function latestLog(): ?AttemptLog
    {
        if ($this->stopped) {
            return null;
        }

        // create it if it doesn't exist yet (relevant for the first attempt)
        $this->storeAttemptLog();

        $attemptNumber = $this->getAttemptNumber();
        return $this->attemptLogs[$attemptNumber];
    }

    /**
     * Retrieve all of the AttemptLog logs.
     *
     * @return AttemptLog[]
     */
    public function logs(): array
    {
        return array_values($this->attemptLogs);
    }

    /**
     * Record the history of attempts.
     *
     * @return void
     */
    private function storeAttemptLog(): void
    {
        $attemptNumber = $this->getAttemptNumber();

        if (isset($this->attemptLogs[$attemptNumber])) {
            return;
        }

        $occurredAt = ($attemptNumber == 1)
            ? $this->firstAttemptOccurredAt
            : null;
        $occurredAt ??= new DateTime();




        $this->attemptLogs[$attemptNumber] = new AttemptLog(
            $attemptNumber,
            $this->maxAttempts,
            $this->firstAttemptOccurredAt,
            $occurredAt,
            $this->nextDelay,
            null, // not known yet
            $this->overallDelay,
            null, // not known yet
            $this->unitType,
        );

        if ($attemptNumber > 1) {

            $prevAttemptLog = $this->attemptLogs[$attemptNumber - 1];

            $diffInSeconds = Support::timeDiff($prevAttemptLog->thisAttemptOccurredAt(), $occurredAt);
            $diffInUnits = Support::convertTimespan($diffInSeconds, Settings::UNIT_SECONDS, $this->unitType);
            $workingTime = $diffInUnits - $this->nextDelay;
            $prevAttemptLog->setWorkingTime($workingTime);

            $diffInSeconds = Support::timeDiff($this->firstAttemptOccurredAt, $occurredAt);
            $diffInUnits = Support::convertTimespan($diffInSeconds, Settings::UNIT_SECONDS, $this->unitType);
            $overallWorkingTime = $diffInUnits - $this->overallDelay;
            $prevAttemptLog->setOverallWorkingTime($overallWorkingTime);
        }
    }





    /**
     * Retrieve the current attempt number.
     *
     * @return integer
     */
    public function getAttemptNumber(): int
    {
        return $this->attemptNumber ?? 1;
    }



    /**
     * Get the most recently calculated delay.
     *
     * @return integer|float|null
     */
    public function getDelay(): int|float|null
    {
        return $this->nextDelay;
    }

    /**
     * Get the most recently calculated delay, in seconds.
     *
     * @return integer|float|null
     */
    public function getDelayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the most recently calculated delay, in milliseconds.
     *
     * @return integer|null
     */
    public function getDelayInMs(): ?int
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the most recently calculated delay, in microseconds.
     *
     * @return integer|null
     */
    public function getDelayInUs(): ?int
    {
        return Support::convertTimespan($this->nextDelay, $this->unitType, Settings::UNIT_MICROSECONDS);
    }





    /**
     * Run through the backoff process and report the results.
     *
     * @internal - For testing purposes.
     *
     * @param integer $maxSteps The maximum number of steps to run through.
     * @return array<string,array<float|integer|null>|integer>
     */
    public function generateTestSequence(int $maxSteps): array
    {
        // get sleep() to record the delays
        $this->recordForTest = true;

        /** @infection-ignore-all $count-- */
        for ($count = 0; $count < $maxSteps; $count++) {
            if (!$this->step()) {
                break;
            }
        }

        return [
            'delay' => $this->recordedDelays,
            'delayInSeconds' => $this->recordedDelaysInSeconds,
            'delayInMs' => $this->recordedDelaysInMs,
            'delayInUs' => $this->recordedDelaysInUs,
            'sleepCallCount' => $this->sleepCallCount,
            'actualTimesSlept' => $this->actualTimesSlept,
        ];
    }
}
