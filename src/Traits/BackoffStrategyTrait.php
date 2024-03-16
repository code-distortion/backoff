<?php

namespace CodeDistortion\Backoff\Traits;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\DelayCalculator;
use CodeDistortion\Backoff\Support\Support;
use DateTime;

/**
 * The main class in this backoff library. This represents a backoff strategy, which:
 * - contains the settings used to apply a backoff strategy,
 * - implements the logic to calculate the delays,
 * - and performs the sleeps.
 */
trait BackoffStrategyTrait
{
    // settings
    // - this is here because they're not nullable, but null can be passed to the constructor indicating they should
    //   "use the default" value
    // - the rest of the settings are defined in the constructor

    /** @var string The unit type to use (from Settings::UNIT_XXX). */
    protected string $unitType;



    // working variables

    /** @var boolean Whether the backoff strategy has started yet. */
    private bool $started;

    /** @var boolean Whether the backoff strategy should stop. */
    private bool $stopped;

    /** @var integer|null The retry attempt count. */
    private ?int $attemptNumber;

    /** @var DelayCalculator|null Used to calculate the delays. */
    private ?DelayCalculator $delayCalculator;

    /** @var integer|null An hrtime(true) point in time to use as an anchor point to sleep from. To improve accuracy. */
    private ?int $sleepStart;

    /** @var integer|float|null The sum of all delays that have occurred. */
    private int|float|null $overallDelay = null;

    /** @var integer|float|null The sum of all the time spent performing the attempts. */
    private int|float|null $overallWorkingTime = null;

    /** @var DateTime The time this instance was created. */
    private Datetime $instantiatedAt;

    /** @var DateTime|null The time the first attempt occurred. */
    private ?Datetime $firstAttemptOccurredAt;

    /** @var array<integer,AttemptLog> The history of attempts. */
    protected array $attemptLogs = [];



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
     * @param BackoffAlgorithmInterface $backoffAlgorithm    The backoff algorithm to use.
     * @param JitterInterface|null      $jitter              The jitter to apply (default: no jitter).
     * @param integer|null              $maxAttempts         The maximum number of attempts to allow - null for infinite
     *                                                       (default: null).
     * @param integer|float|null        $maxDelay            The maximum delay to allow (optional).
     * @param string|null               $unitType            The unit type to use
     *                                                       (from Settings::UNIT_XXX, default: seconds).
     * @param boolean                   $runsAtStartOfLoop   Whether the backoff strategy will be called before the
     *                                                       first attempt is actually made or not.
     * @param boolean                   $immediateFirstRetry Whether to insert a 0 delay as the first retry delay.
     * @param boolean                   $delaysEnabled       Whether delays are allowed or not.
     * @param boolean                   $retriesEnabled      Whether retries are allowed or not.
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public function __construct(
        protected BackoffAlgorithmInterface $backoffAlgorithm,
        protected ?JitterInterface $jitter = null,
        protected ?int $maxAttempts = null,
        protected int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        protected bool $runsAtStartOfLoop = false,
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
     * Alternative constructor - to make chaining easier.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm    The backoff algorithm to use.
     * @param JitterInterface|null      $jitter              The jitter to apply (default: no jitter).
     * @param integer|null              $maxAttempts         The maximum number of attempts to allow - null for infinite
     *                                                       (default: null).
     * @param integer|float|null        $maxDelay            The maximum delay to allow (optional).
     * @param string|null               $unitType            The unit type to use
     *                                                       (from Settings::UNIT_XXX, default: seconds).
     * @param boolean                   $runsAtStartOfLoop   Whether the backoff strategy will be called before the
     *                                                       first attempt is actually made or not.
     * @param boolean                   $immediateFirstRetry Whether to insert a 0 delay as the first retry delay.
     * @param boolean                   $delaysEnabled       Whether delays are allowed or not.
     * @param boolean                   $retriesEnabled      Whether retries are allowed or not.
     * @return static
     * @throws BackoffInitialisationException When $unitType is invalid.
     */
    public static function new(
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?JitterInterface $jitter = null,
        ?int $maxAttempts = null,
        int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        bool $runsAtStartOfLoop = false,
        bool $immediateFirstRetry = false,
        bool $delaysEnabled = true,
        bool $retriesEnabled = true,
    ): static {

        return new static(
            $backoffAlgorithm,
            $jitter,
            $maxAttempts,
            $maxDelay,
            $unitType,
            $runsAtStartOfLoop,
            $immediateFirstRetry,
            $delaysEnabled,
            $retriesEnabled,
        );
    }





    /**
     * Reset the backoff strategy to its initial state.
     *
     * @return $this
     */
    public function reset(): static
    {
        // working variables
        $this->started = false;
        $this->assessInitialStoppedState(); // stop straight away if no attempts are allowed
        $this->attemptNumber = null;
        $this->delayCalculator = null;
        $this->sleepStart = null;
        $this->instantiatedAt = new DateTime();
        $this->firstAttemptOccurredAt = null;

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
     * Reassess if the backoff strategy should be stopped because of the max attempts.
     *
     * @return void
     */
    private function assessInitialStoppedState(): void
    {
        $this->stopped = (!is_null($this->maxAttempts)) && ($this->maxAttempts <= 0);
    }

    /**
     * Retrieve the DelayCalculator instance, creating it if necessary.
     *
     * @return DelayCalculator
     */
    private function delayCalculator(): DelayCalculator
    {
        return $this->delayCalculator ??= new DelayCalculator(
            $this->backoffAlgorithm,
            $this->jitter,
            $this->maxAttempts,
            $this->maxDelay,
            $this->unitType,
            $this->immediateFirstRetry,
            $this->delaysEnabled,
        );
    }





    /**
     * Record that this backoff strategy has started.
     *
     * @return void
     */
    private function start(): void
    {
        // start the logs again if this is the first attempt
        if (!$this->started) {
            $this->attemptLogs = [];
        }
        $this->started = true;
    }

    /**
     * Run a step of the backoff process.
     *
     * @return boolean
     */
    public function step(): bool
    {
        // set $this->sleepStart here as the first thing, for increased accuracy
        $this->sleepStart = hrtime(true);

        $this->calculate();

        return $this->sleep();
    }





    /**
     * Calculate the delay needed before retrying an action next.
     *
     * @return boolean
     */
    public function calculate(): bool
    {
        $this->start();


        // don't continue if we've already stopped
        if ($this->stopped) {
            return false;
        }

        // if this is being called at the beginning of the loop & before the first attempt
        // then don't calculate the delay
        if (($this->runsAtStartOfLoop) && (is_null($this->attemptNumber))) {
            $this->attemptNumber = 1;
            return true;
        }

//        /* * @infection-ignore-all $this->attemptNumber = 1; */
        $this->attemptNumber ??= 1;
        $this->attemptNumber++;

        if (!$this->canContinue()) {
            $this->stopped = true;
            return false;
        }

        return true;
    }

    /**
     * Sleep for the calculated period.
     *
     * @return boolean
     */
    public function sleep(): bool
    {
        // use $this->sleepStart that was set earlier in ->step() (if available), for increased accuracy
        $start = $this->sleepStart
            ?? hrtime(true);
        $this->sleepStart = null; // reset it



        $this->start();



        if ($this->recordForTest) {
            $this->sleepCallCount++;
        }

        if ($this->stopped) {
            return false;
        }



//        $this->recordAttemptLogTimings();



        if ($this->recordForTest) {
            $this->recordedDelays[] = $this->getDelay();
            $this->recordedDelaysInSeconds[] = $this->getDelayInSeconds();
            $this->recordedDelaysInMs[] = $this->getDelayInMs();
            $this->recordedDelaysInUs[] = $this->getDelayInUs();
        }

        $microsecondDelay = $this->getDelayInUs();
        if (is_null($microsecondDelay)) {
            return true; // no delay has been calculated yet
        }

        $this->overallDelay ??= 0;
        $this->overallDelay += $this->getDelay();

        $this->performSleep($start, $microsecondDelay);
//        $this->storeAttemptLog();

        return true;
    }

    /**
     * Sleep until a specific time, in nanoseconds.
     *
     * @infection-ignore-all many things
     *
     * @param integer       $start        The hrtime(true) start time.
     * @param integer|float $microseconds The number of microseconds to sleep for (will be converted to nanoseconds).
     * @return void
     */
    private function performSleep(int $start, int|float $microseconds): void
    {
        if ($this->recordForTest) {
            $this->actualTimesSlept++;
            return;
        }

        $until = $start + (int) ($microseconds * 1000);

        do {

            // re/calculate the remaining time each iteration.
            // this is to take overhead into account and avoid cumulative errors
            // (PHP's handling of the signal, other system-related delays, etc.)
            // when time_nanosleep() is interrupted by a signal and returns early
            //
            // it also means that if the delay is 0, no sleep will actually occur

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
     * Start recording the AttemptLog.
     *
     * @return $this
     * @throws BackoffRuntimeException When Backoff has stopped.
     */
    public function startOfAttempt(): static
    {
        $this->start();

        if ($this->stopped) {
            throw BackoffRuntimeException::startOfAttemptNotAllowed();
        }

        $attemptNumber = $this->currentAttemptNumber();

        // start the logs again if this is the first attempt
        if ($attemptNumber <= 1) {
            $this->attemptLogs = [];
        }

        // todo - add this back in
//        // close the last one of if needed
//        if (isset($this->attemptLogs[$attemptNumber - 1])) {
//            $this->endOfAttempt();
//        }

        $this->attemptLogs[$attemptNumber] = new AttemptLog(
            $attemptNumber,
            $this->maxAttempts,
            $this->firstAttemptOccurredAt ?? $this->instantiatedAt,
            $this->firstAttemptOccurredAt ?? $this->instantiatedAt,
            null, // not known yet
            null, // not known yet
            $this->delayCalculator()->getJitteredDelay($attemptNumber),
            $this->delayCalculator()->getJitteredDelay($attemptNumber + 1),
            $this->overallDelay,
            $this->unitType,
        );



        // record the DateTimes as close to when they'll be used as possible
        if ($attemptNumber == 1) {
            $this->firstAttemptOccurredAt = new DateTime();
            $this->attemptLogs[$attemptNumber]->setFirstAttemptOccurredAt($this->firstAttemptOccurredAt);
        }

        $occurredAt = ($attemptNumber == 1)
            ? $this->firstAttemptOccurredAt
            : new DateTime();
        $this->attemptLogs[$attemptNumber]->setThisAttemptOccurredAt($occurredAt);

        return $this;
    }

    /**
     * Finalise the current AttemptLog.
     *
     * @return $this
     * @throws BackoffRuntimeException When ->startOfAttempt() was not called first.
     */
    public function endOfAttempt(): static
    {
        $finishedAt = new DateTime(); // record the time closest to when this method was called

        $attemptNumber = $this->currentAttemptNumber();
        $attemptLog = $this->attemptLogs[$attemptNumber] ?? null;

        if ((!$this->started) || (is_null($attemptLog))) {
            throw BackoffRuntimeException::attemptLogHasNotStarted();
        }

        // if the working time has already been set, don't update it.
        // this allows for the earliest time (this was triggered) is used
        if (!is_null($attemptLog->workingTime())) {
            return $this;
        }

        // calculate and record the working time
        $diffInSeconds = Support::timeDiff($attemptLog->thisAttemptOccurredAt(), $finishedAt);
        $diffInUnits = Support::convertTimespan($diffInSeconds, Settings::UNIT_SECONDS, $this->unitType);
        $attemptLog->setWorkingTime($diffInUnits);

        // add this new working time, to the overall working time
        $prevAttemptLog = $this->attemptLogs[$attemptNumber - 1] ?? null;
        if ($prevAttemptLog) {
            $diffInUnits += $prevAttemptLog->overallWorkingTime();
        }
        $attemptLog->setOverallWorkingTime($diffInUnits);

        return $this;
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
     * Retrieve the latest AttemptLog, representing the most current attempt.
     *
     * @return AttemptLog|null
     */
    public function currentLog(): ?AttemptLog
    {
        if (!$this->started) {
            return null;
        }

        if ($this->stopped) {
            return null;
        }

//        // create it if it doesn't exist yet (relevant for the first attempt)
//        $this->storeAttemptLog();

        return $this->attemptLogs[$this->currentAttemptNumber()]
            ?? null;
    }





    /**
     * Check if the backoff strategy has started yet.
     *
     * @return boolean
     */
    private function hasStarted(): bool
    {
        return $this->started;
    }

    /**
     * Determine if the number of attempts has been exceeded.
     *
     * @return boolean
     */
    private function canContinue(): bool
    {
        if (!$this->retriesEnabled) {
            return false;
        }

        // check if the backoff strategy thinks it's time to stop
        if ($this->delayCalculator()->shouldStop($this->attemptNumber)) {
            return false;
        }

        return true;
    }





    /**
     * Check if the backoff strategy should stop.
     *
     * @return boolean
     */
    public function hasStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Retrieve the current attempt number.
     *
     * @return integer
     */
    public function currentAttemptNumber(): int
    {
        return $this->attemptNumber ?? 1;
    }

    /**
     * Find out if the backoff strategy is currently on the first step.
     *
     * @return boolean
     */
    public function isFirstAttempt(): bool
    {
        return $this->currentAttemptNumber() == 1;
    }

    /**
     * Find out if the backoff strategy is currently on the last step.
     *
     * @return boolean
     */
    public function isLastAttempt(): bool
    {
        $this->start();

        if ($this->stopped) {
            return true;
        }

        if ($this->delayCalculator()->shouldStop($this->currentAttemptNumber() + 1)) {
            return true;
        }

        return false;
    }





    /**
     * Get the unit-of-measure being used.
     *
     * @return string
     */
    public function getUnitType(): string
    {
        return $this->unitType;
    }





    /**
     * Get the most recently calculated delay.
     *
     * @return integer|float|null
     */
    public function getDelay(): int|float|null
    {
        $this->start();

        return !$this->stopped
            ? $this->delayCalculator()->getJitteredDelay($this->currentAttemptNumber())
            : null;
    }

    /**
     * Get the most recently calculated delay, in seconds.
     *
     * @return integer|float|null
     */
    public function getDelayInSeconds(): int|float|null
    {
        return Support::convertTimespan($this->getDelay(), $this->unitType, Settings::UNIT_SECONDS);
    }

    /**
     * Get the most recently calculated delay, in milliseconds.
     *
     * @return integer|float|null
     */
    public function getDelayInMs(): int|float|null
    {
        return Support::convertTimespan($this->getDelay(), $this->unitType, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the most recently calculated delay, in microseconds.
     *
     * @return integer|float|null
     */
    public function getDelayInUs(): int|float|null
    {
        return Support::convertTimespan($this->getDelay(), $this->unitType, Settings::UNIT_MICROSECONDS);
    }





    /**
     * Retrieve the delays this backoff generates.
     *
     * Note: This generates the set of delays based on the *retry* count, not the *attempt* count. So the delay 1 will
     * be the one used before the second attempt (which is the first retry).
     *
     * Note: When you call this method, backoff generates the same delays it did previously for the same retry numbers.
     * It maintains this history because some backoff strategies base their delays on previous delays (e.g. the
     * decorrelated strategy does this), so their values are important. To generate a new set of delays, call
     * $backoff->reset() first.
     *
     * @param integer      $retryStart The retry to start at.
     * @param integer|null $retryStop  The retry to stop at.
     * @return integer|float|null|array<integer|float|null>
     */
    public function simulate(int $retryStart, ?int $retryStop = null): int|float|null|array
    {
        return $this->generateSimulationSequence($retryStart, $retryStop, $this->unitType);
    }

    /**
     * Get the most recently calculated delay, in seconds.
     *
     * @param integer      $retryStart The retry to start at.
     * @param integer|null $retryStop  The retry to stop at.
     * @return integer|float|null|array<integer|float|null>
     */
    public function simulateInSeconds(int $retryStart, ?int $retryStop = null): int|float|null|array
    {
        return $this->generateSimulationSequence($retryStart, $retryStop, Settings::UNIT_SECONDS);
    }

    /**
     * Get the most recently calculated delay, in milliseconds.
     *
     * @param integer      $retryStart The retry to start at.
     * @param integer|null $retryStop  The retry to stop at.
     * @return integer|float|null|array<integer|float|null>
     */
    public function simulateInMs(int $retryStart, ?int $retryStop = null): int|float|null|array
    {
        return $this->generateSimulationSequence($retryStart, $retryStop, Settings::UNIT_MILLISECONDS);
    }

    /**
     * Get the most recently calculated delay, in microseconds.
     *
     * @param integer      $retryStart The retry to start at.
     * @param integer|null $retryStop  The retry to stop at.
     * @return integer|float|null|array<integer|float|null>
     */
    public function simulateInUs(int $retryStart, ?int $retryStop = null): int|float|null|array
    {
        return $this->generateSimulationSequence($retryStart, $retryStop, Settings::UNIT_MICROSECONDS);
    }

    /**
     * Generate a sequence of delays for simulation purposes.
     *
     * @param integer      $retryStart    The retry to start at.
     * @param integer|null $retryStop     The retry to stop at.
     * @param string       $unitOfMeasure The unit of measure to use (from Settings::UNIT_XXX).
     * @return integer|float|null|array<integer|float|null>
     */
    private function generateSimulationSequence(
        int $retryStart,
        ?int $retryStop,
        string $unitOfMeasure
    ): int|float|null|array {

        if ($retryStart < 1) {
            return [];
        }

        $returnSingleDelay = is_null($retryStop);
        $retryStop ??= $retryStart;

        if ($retryStop < $retryStart) {
            return [];
        }

        $this->start();

        $return = [];
        for ($retryNumber = $retryStart; $retryNumber <= $retryStop; $retryNumber++) {

            // just to be explicit that we're passing the "attempt" number instead of the "retry" number
            $attemptNumber = $retryNumber + 1;

            $delay = $this->delayCalculator()->getJitteredDelay($attemptNumber);
            $return[$retryNumber] = Support::convertTimespan($delay, $this->unitType, $unitOfMeasure);
        }

        return $returnSingleDelay
            ? reset($return)
            : $return;
    }





    /**
     * Run through the backoff process and report the results.
     *
     * @internal - For testing purposes.
     *
     * @param integer $maxSteps The maximum number of steps to run through.
     * @return array<string,array<integer|float|null>|integer>
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
