<?php

namespace CodeDistortion\Backoff\Interfaces\todo;

use CodeDistortion\Backoff\AttemptLog;
use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Settings;

/**
 * Interface for the backoff strategy class.
 */
interface BackoffStrategyInterface
{
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
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?JitterInterface $jitter = null,
        ?int $maxAttempts = null,
        int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        bool $runsAtStartOfLoop = false,
        bool $immediateFirstRetry = false,
        bool $delaysEnabled = true,
        bool $retriesEnabled = true,
    );



    /**
     * Reset the backoff strategy to its initial state.
     *
     * @return $this
     */
    public function reset(): self;





    /**
     * Run a step of the backoff process.
     *
     * @return boolean
     */
    public function step(): bool;





    /**
     * Calculate the delay needed before retrying an action next.
     *
     * @return boolean
     */
    public function calculate(): bool;





    /**
     * Check if the backoff strategy should stop.
     *
     * @return boolean
     */
    public function hasStopped(): bool;

    /**
     * Sleep for the calculated period.
     *
     * @return boolean
     */
    public function sleep(): bool;



    /**
     * Retrieve the latest AttemptLog, representing the most current attempt.
     *
     * @return AttemptLog|null
     */
    public function currentLog(): ?AttemptLog;

    /**
     * Retrieve all of the AttemptLog logs.
     *
     * @return AttemptLog[]
     */
    public function logs(): array;





    /**
     * Retrieve the current attempt number.
     *
     * @return integer
     */
    public function currentAttemptNumber(): int;



    /**
     * Get the most recently calculated delay.
     *
     * @return integer|float|null
     */
    public function getDelay(): int|float|null;

    /**
     * Get the most recently calculated delay, in seconds.
     *
     * @return integer|float|null
     */
    public function getDelayInSeconds(): int|float|null;

    /**
     * Get the most recently calculated delay, in milliseconds.
     *
     * @return integer|null
     */
    public function getDelayInMs(): ?int;

    /**
     * Get the most recently calculated delay, in microseconds.
     *
     * @return integer|null
     */
    public function getDelayInUs(): ?int;





    /**
     * Run through the backoff process and report the results.
     *
     * @internal - For testing purposes.
     *
     * @param integer $maxSteps The maximum number of steps to run through.
     * @return array<string,array<float|integer|null>|integer>
     */
    public function generateTestSequence(int $maxSteps): array;
}
