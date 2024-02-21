<?php

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Settings;

/**
 * Interface for the backoff handler class.
 */
interface BackoffHandlerInterface
{
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
     * @throws BackoffInitialisationException When an invalid $unitType is specified.
     */
    public function __construct(
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?JitterInterface $jitter = null,
        ?int $maxAttempts = null,
        int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        bool $runsBeforeFirstAttempt = false,
        bool $immediateFirstRetry = false,
        bool $delaysEnabled = true,
        bool $retriesEnabled = true,
    );



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
    public function performBackoffLogic(): bool;

    /**
     * Check if the backoff handler should stop.
     *
     * @return boolean
     */
    public function shouldStop(): bool;

    /**
     * Sleep for the calculated period.
     *
     * @return boolean
     */
    public function sleep(): bool;





    /**
     * Retrieve the current attempt number.
     *
     * @return integer
     */
    public function getAttemptNumber(): int;

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
