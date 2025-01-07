<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Traits;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Support\DelayCalculator;
use CodeDistortion\Backoff\Support\Support;
use CodeDistortion\Backoff\Support\DelayTracker;
use DateTime;

/**
 * Adds backoff-strategy functionality.
 * - contains the settings used to apply a backoff strategy,
 * - implements the delay logic,
 * - performs the delay sleeps,
 * - tracks information about each attempt (e.g. for logging).
 */
trait BackoffStrategyTrait
{
    // settings

    // - $unitType is here because it's not nullable, BUT null can be passed to the constructor indicating it should
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



    // working variables used for testing

    /** @var DelayTracker|null Tracks delays, when running tests. */
    private ?DelayTracker $delayTracker;



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
        ?string $unitType = null,
        protected bool $runsAtStartOfLoop = false,
        protected bool $immediateFirstRetry = false,
        protected bool $delaysEnabled = true,
        protected bool $retriesEnabled = true,
    ) {

        $this->unitType = $unitType ?? Settings::UNIT_SECONDS;
        if (!in_array($this->unitType, Settings::ALL_UNIT_TYPES, true)) {
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
        ?string $unitType = null,
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

        // used to track delays, for testing
        $this->delayTracker = null;

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
        $maxRetries = !is_null($this->maxAttempts)
            ? $this->maxAttempts - 1
            : null;

        return $this->delayCalculator ??= new DelayCalculator(
            $this->backoffAlgorithm,
            $this->jitter,
            $maxRetries,
            $this->maxDelay,
            $this->unitType,
            $this->immediateFirstRetry,
            $this->delaysEnabled,
        );
    }





    /**
     * Proceed to the next attempt.
     *
     * @param boolean $sleep Whether to perform the sleep or not.
     * @return boolean
     */
    public function step(bool $sleep = true): bool
    {
        // set $this->sleepStart here as the first thing, for increased accuracy
        $this->sleepStart = hrtime(true);

        return $sleep
            ? $this->proceed() && $this->sleep()
            : $this->proceed();
    }





    /**
     * Record that this backoff strategy has started.
     *
     * @return void
     */
    private function starting(): void
    {
        // start the logs again if this is the first attempt
        if (!$this->started) {
            $this->attemptLogs = [];
        }

        // don't start if we've already stopped (i.e. 0 attempts)
        if ($this->stopped) {
            return;
        }

        $this->started = true;
    }

    /**
     * Proceed to the next attempt (if allowed).
     *
     * @return boolean
     */
    private function proceed(): bool
    {
        $this->starting();

        // don't continue if we've already stopped
        // @infection-ignore-all false -> true (timeout, mutant didn't escape)
        if ($this->stopped) {
            return false;
        }

        // if this is being called at the beginning of the loop & before the first attempt
        // then don't calculate the delay
        // @infection-ignore-all LogicalAndSingleSubExprNegation and LogicalAndNegation (timeout, mutant didn't escape)
        if (($this->runsAtStartOfLoop) && (is_null($this->attemptNumber))) {
            $this->attemptNumber = 1;
            return true;
        }

        // @infection-ignore-all $this->attemptNumber = 1 (timeout, mutant didn't escape)
        $this->attemptNumber ??= 1;
        // @infection-ignore-all $this->attemptNumber-- (timeout, mutant didn't escape)
        $this->attemptNumber++;

        if (!$this->calculateCanContinue()) {
            $this->stopped = true;
            $this->attemptNumber--; // don't count this attempt
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
        // $this->sleepStart was set earlier if ->step() was called. This is to improve accuracy
        $start = $this->sleepStart
            ?? hrtime(true);
        $this->sleepStart = null; // reset it



        $this->delayTracker?->recordSleepCall();



        $this->starting();

        if ($this->stopped) {
            return false;
        }



        $microsecondDelay = $this->getDelayInUs();

        $this->delayTracker?->recordDelay($this->getDelay());
        $this->delayTracker?->recordDelayInSeconds($this->getDelayInSeconds());
        $this->delayTracker?->recordDelayInMs($this->getDelayInMs());
        $this->delayTracker?->recordDelayInUs($microsecondDelay);

        if (is_null($microsecondDelay)) {
            return true; // no delay has been calculated yet
        }

        $this->overallDelay ??= 0;
        $this->overallDelay += $this->getDelayAsNumber();

        $this->performSleep($start, $microsecondDelay);

        return true;
    }

    /**
     * Sleep until a specific time, in nanoseconds.
     *
     * @param integer       $start        The hrtime(true) start time.
     * @param integer|float $microseconds The number of microseconds to sleep for (will be converted to nanoseconds).
     * @return void
     */
    private function performSleep(int $start, int|float $microseconds): void
    {
        if (!is_null($this->delayTracker)) {
            $this->delayTracker->recordActualSleep();
            return;
        }

        // @infection-ignore-all IncrementInteger
        $until = $start + intval($microseconds * 1000);

        // @infection-ignore-all true -> false in while(...)
        do {

            // re/calculate the remaining time each iteration
            // this is to take overhead into account and avoid cumulative errors
            // when time_nanosleep() is interrupted by a signal and returns early
            // (PHP's handling of the signal, other system-related delays, etc.)
            //
            // it also means that if the delay is 0, no sleep will actually occur

            $remaining = $until - hrtime(true);
            // @infection-ignore-all LessThanOrEqualTo
            if ($remaining <= 0) {
                return;
            }

            // pick the whole seconds
            // @infection-ignore-all IncrementInteger
            $remainingSeconds = intdiv($remaining, 1_000_000_000);
            // pick the remaining nanoseconds
            // @infection-ignore-all IncrementInteger
            $remainingNanoseconds = $remaining % 1_000_000_000;

            $result = time_nanosleep($remainingSeconds, $remainingNanoseconds);

            // the sleep completed without interruption
            // @infection-ignore-all true -> false
            if ($result === true) {
                return;
            }

            // the sleep was interrupted
            // continue the loop, which recalculate the remaining time
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
        $this->starting();

        if ($this->stopped) {
            throw BackoffRuntimeException::startOfAttemptNotAllowed();
        }

        // (will always be an integer because $this->stopped is checked for above)
        $attemptNumber = $this->currentAttemptAsNumber();

        // start the logs again if this is the first attempt
        if ($attemptNumber < 2) {
            $this->attemptLogs = [];
        }

        // finalise the previous AttemptLog if needed
        $prevAttemptLog = $this->attemptLogs[$attemptNumber - 1] ?? null;
        $this->endAnAttempt($prevAttemptLog);

        $tempDateTime = new DateTime();

        $this->attemptLogs[$attemptNumber] = new AttemptLog(
            $attemptNumber,
            $this->maxAttempts,
            $this->firstAttemptOccurredAt ?? $tempDateTime, // start with this, is updated below
            $tempDateTime, // start with this, is updated below
            null, // not known yet
            null, // not known yet
            $this->delayCalculator()->getJitteredDelay($attemptNumber - 1),
            $this->delayCalculator()->getJitteredDelay($attemptNumber),
            $this->overallDelay,
            $this->unitType,
        );



        // record the DateTimes as close to when they'll be used as possible

        // resolve $firstAttemptOccurredAt on the first attempt
        if ($attemptNumber === 1) {
            $this->firstAttemptOccurredAt = new DateTime();
            $this->attemptLogs[$attemptNumber]->setFirstAttemptOccurredAt($this->firstAttemptOccurredAt);
        }

        // resolve $thisAttemptOccurredAt
        $occurredAt = ($attemptNumber === 1) && ($this->firstAttemptOccurredAt !== null)
            ? $this->firstAttemptOccurredAt // set to be the same as $firstAttemptOccurredAt on the first attempt
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
        $attemptNumber = $this->currentAttemptNumber();
        $attemptLog = $this->attemptLogs[$attemptNumber] ?? null;

        if ((!$this->started) || (is_null($attemptLog))) {
            throw BackoffRuntimeException::attemptLogHasNotStarted();
        }

        $this->endAnAttempt($attemptLog);

        return $this;
    }

    /**
     * Finalise the passed AttemptLog.
     *
     * @param AttemptLog|null $attemptLog The AttemptLog to finalise.
     * @return void
     */
    private function endAnAttempt(?AttemptLog $attemptLog): void
    {
        $finishedAt = new DateTime(); // record the time closest to when this method was called

        if (is_null($attemptLog)) {
            return;
        }

        // if the working time has already been set, don't update it.
        // this allows for the earliest time (this was triggered) is used
        if (!is_null($attemptLog->workingTime())) {
            return;
        }

        // calculate and record the working time
        $diffInSeconds = Support::timeDiff($attemptLog->thisAttemptOccurredAt(), $finishedAt);
        $diffInUnits = Support::convertTimespanAsNumber($diffInSeconds, Settings::UNIT_SECONDS, $this->unitType);
        $attemptLog->setWorkingTime($diffInUnits);

        // add this new working time, to the overall working time
        $prevAttemptLog = $this->attemptLogs[$attemptLog->attemptNumber() - 1] ?? null;
        if (!is_null($prevAttemptLog)) {
            $diffInUnits += $prevAttemptLog->overallWorkingTimeAsNumber();
        }
        $attemptLog->setOverallWorkingTime($diffInUnits);
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
     * Check if the backoff strategy should stop.
     *
     * @return boolean
     */
    public function hasStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Check if the backoff strategy should continue.
     *
     * @return boolean
     */
    public function canContinue(): bool
    {
        return !$this->stopped;
    }

    /**
     * Determine if the number of attempts has been exceeded.
     *
     * @return boolean
     */
    private function calculateCanContinue(): bool
    {
        if (!$this->retriesEnabled) {
            return false;
        }

        // check if the backoff strategy thinks it's time to stop
        if ($this->delayCalculator()->shouldStop($this->currentRetryAsNumber())) {
            // @infection-ignore-all false -> true (timeout, mutant didn't escape)
            return false;
        }

        return true;
    }





    /**
     * Retrieve the current attempt number.
     *
     * @return integer|null
     */
    public function currentAttemptNumber(): ?int
    {
        // hasn't started yet
        if ((!$this->started) && ($this->runsAtStartOfLoop)) {
            return null;
        }

        // @infection-ignore-all !is_null(..) -> is_null(..) (timeout, mutant didn't escape)
        if (!is_null($this->attemptNumber)) {
            return $this->attemptNumber;
        }

        // has stopped
        return !$this->stopped
            ? 1
            : null;
    }

    /**
     * Retrieve the current attempt number.
     *
     * Used internally to remove some infection mutants.
     *
     * @return integer
     */
    private function currentAttemptAsNumber(): int
    {
        return $this->currentAttemptNumber() ?? 0;
    }

    /**
     * Retrieve the current retry number.
     *
     * @return integer|null
     */
    private function currentRetryNumber(): ?int
    {
        $attemptNumber = $this->currentAttemptNumber();

        return !is_null($attemptNumber)
            ? $attemptNumber - 1
            : null;
    }

    /**
     * Retrieve the current retry number.
     *
     * Used internally to remove some infection mutants.
     *
     * @return integer
     */
    private function currentRetryAsNumber(): int
    {
        return $this->currentRetryNumber() ?? 0;
    }

    /**
     * Find out if the backoff strategy is currently on the first step.
     *
     * @return boolean
     */
    public function isFirstAttempt(): bool
    {
        return $this->currentAttemptNumber() === 1;
    }

    /**
     * Find out if the backoff strategy is currently on the last step.
     *
     * @return boolean
     */
    public function isLastAttempt(): bool
    {
        $this->starting();

        if ($this->stopped) {
            return true;
        }

        // (will always be an integer because $this->stopped has been checked above)
        $nextRetryNumber = $this->currentRetryAsNumber() + 1;

        if ($this->delayCalculator()->shouldStop($nextRetryNumber)) {
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
        $this->starting();

        if ($this->stopped) {
            return null;
        }

        return $this->delayCalculator()->getJitteredDelay($this->currentRetryAsNumber());
    }

    /**
     * Get the most recently calculated delay.
     *
     * Used internally to remove some infection mutants.
     *
     * @return integer|float
     */
    private function getDelayAsNumber(): int|float
    {
        return $this->getDelay() ?? 0;
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
     * @return integer|float|null|array<int<1,max>,integer|float|null>
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

        $this->starting();

        $return = [];
        // @infection-ignore-all $retryNumber++ -> $retryNumber-- (takes up too much memory)
        for ($retryNumber = $retryStart; $retryNumber <= $retryStop; $retryNumber++) {

            $delay = $this->delayCalculator()->getJitteredDelay($retryNumber);
            $return[$retryNumber] = Support::convertTimespan($delay, $this->unitType, $unitOfMeasure);
        }

        if ($returnSingleDelay) {
            $return = reset($return);
            /** @var integer|float|null $return */
        }

        return $return;
    }





    /**
     * Use a DelayTracker to track delays.
     *
     * @internal - For package testing purposes.
     *
     * @return DelayTracker
     */
    public function useTracker(): DelayTracker
    {
        return $this->delayTracker = new DelayTracker();
    }

    /**
     * Run through the backoff process and report the results using a DelayTracker instance.
     *
     * @internal - For package testing purposes.
     *
     * @param integer $maxSteps The maximum number of steps to run through.
     * @return DelayTracker
     */
    public function generateTestSequence(int $maxSteps): DelayTracker
    {
        $delayTracker = $this->useTracker();

        // @infection-ignore-all $count++ -> $count-- (timeout, mutant didn't escape)
        for ($count = 0; $count < $maxSteps; $count++) {
            $this->step();
        }

        return $delayTracker;
    }
}
