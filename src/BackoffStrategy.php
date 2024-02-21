<?php

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\EqualJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Strategies\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\DecorrelatedBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\ExponentialBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\FibonacciBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\NoBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\PolynomialBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\RandomBackoffAlgorithm;
use CodeDistortion\Backoff\Strategies\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;
use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\JitterInterface;

/**
 * Class that implements a backoff strategy.
 *
 * This class adds methods to assist with instantiation and configuration of the backoff strategy.
 *
 * The parent class is Support/AbstractBackoffHandler implements the backoff strategy logic.
 */
class Backoff extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    // instantiation methods

    /**
     * Alternative constructor.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm       The backoff algorithm to use.
     * @param JitterInterface|null      $jitter                 The jitter to apply (default: no jitter).
     * @param integer|null              $maxAttempts            The maximum number of attempts to allow - null for
     *                                                         infinite (default: null).
     * @param integer|float|null        $maxDelay               The maximum delay to allow (optional).
     * @param string|null               $unitType               The unit type to use
     *                                                         (from Settings::UNIT_XXX, default: seconds).
     * @param boolean                   $runsBeforeFirstAttempt Whether the backoff handler should start with the first
     *                                                         attempt, meaning no initial delay.
     * @param boolean                   $immediateFirstRetry    Whether to insert a 0 delay as the first retry delay.
     * @param boolean                   $delaysEnabled          Whether delays are allowed or not.
     * @param boolean                  $retriesEnabled         Whether retries are allowed or not.
     * @return self
     * @throws BackoffInitialisationException When an invalid $unitType is specified.
     */
    public static function new(
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?JitterInterface          $jitter = null,
        ?int                      $maxAttempts = null,
        int|float|null            $maxDelay = null,
        ?string                   $unitType = Settings::UNIT_SECONDS,
        bool                      $runsBeforeFirstAttempt = false,
        bool                      $immediateFirstRetry = false,
        bool                      $delaysEnabled = true,
        bool                      $retriesEnabled = true,
    ): self {

        return new self(
            $backoffAlgorithm,
            $jitter,
            $maxAttempts,
            $maxDelay,
            $unitType,
            $runsBeforeFirstAttempt,
            $immediateFirstRetry,
            $delaysEnabled,
            $retriesEnabled,
        );
    }





    // instantiation methods - for each backoff algorithm

    /**
     * Create a new backoff handler using the fixed backoff algorithm.
     *
     * @param integer|float $delay The delay to use.
     * @return self
     */
    public static function fixed(int|float $delay): self
    {
        return new self(
            new FixedBackoffAlgorithm($delay)
        );
    }

    /**
     * Create a new backoff handler using the linear backoff algorithm.
     *
     * @param integer|float      $initialDelay  The initial delay to use.
     * @param integer|float|null $delayIncrease The amount to increase the delay by (optional, falls back to
     *                                          $initialDelay).
     * @return self
     */
    public static function linear(
        int|float $initialDelay,
        int|float|null $delayIncrease = null,
    ): self {

        return new self(
            new LinearBackoffAlgorithm($initialDelay, $delayIncrease)
        );
    }

    /**
     * Create a new backoff handler using the exponential backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param integer|float $factor       The factor to multiply by each time (default 2).
     * @return self
     */
    public static function exponential(
        int|float $initialDelay,
        int|float $factor = 2,
    ): self {

        return new self(
            new ExponentialBackoffAlgorithm($initialDelay, $factor)
        );
    }

    /**
     * Create a new backoff handler using the polynomial backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param integer|float $power        The power to raise the retry number to (default 2).
     * @return self
     */
    public static function polynomial(
        int|float $initialDelay,
        int|float $power = 2,
    ): self {

        return new self(
            new PolynomialBackoffAlgorithm($initialDelay, $power)
        );
    }

    /**
     * Create a new backoff handler using the fibonacci backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param boolean       $includeFirst Whether to include the first value in the Fibonacci sequence or not.
     * @return self
     */
    public static function fibonacci(
        int|float $initialDelay,
        bool $includeFirst = false
    ): self {

        return new self(
            new FibonacciBackoffAlgorithm($initialDelay, $includeFirst)
        );
    }

    /**
     * Create a new backoff handler using the decorrelated backoff algorithm.
     *
     * @param integer|float $baseDelay  The base delay to use.
     * @param integer|float $multiplier The amount to multiply the previous delay by (default 3).
     * @return self
     */
    public static function decorrelated(
        int|float $baseDelay,
        int|float $multiplier = 3,
    ): self {

        return new self(
            new DecorrelatedBackoffAlgorithm($baseDelay, $multiplier)
        );
    }

    /**
     * Create a new backoff handler using the random backoff algorithm.
     *
     * @param integer|float $minDelay The minimum delay to use.
     * @param integer|float $maxDelay The maximum delay to use.
     * @throws BackoffInitialisationException Thrown when the minDelay is greater than the maxDelay.
     * @return self
     */
    public static function random(
        int|float $minDelay,
        int|float $maxDelay,
    ): self {

        return new self(
            new RandomBackoffAlgorithm($minDelay, $maxDelay)
        );
    }

    /**
     * Create a new backoff handler using the sequence backoff algorithm.
     *
     * @param array<integer|float> $delays The sequence of delays to use.
     * @return self
     */
    public static function sequence(array $delays): self
    {
        return new self(
            new SequenceBackoffAlgorithm($delays)
        );
    }

    /**
     * Create a new backoff handler using the callback backoff algorithm.
     *
     * @param callable $callback The callback that will determine the delays to use.
     * @return self
     */
    public static function callback(callable $callback): self
    {
        return new self(
            new CallbackBackoffAlgorithm($callback)
        );
    }

    /**
     * Create a new backoff handler using a custom backoff algorithm.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm The backoff algorithm instance to use.
     * @return self
     */
    public static function custom(BackoffAlgorithmInterface $backoffAlgorithm): self
    {
        return new self($backoffAlgorithm);
    }

    /**
     * Create a new backoff handler using the "noop" backoff algorithm.
     *
     * @return self
     */
    public static function noop(): self
    {
        return new self(
            new NoopBackoffAlgorithm()
        );
    }

    /**
     * Create a new backoff handler using the "no backoff" algorithm.
     *
     * @return self
     */
    public static function none(): self
    {
        return new self(
            new NoBackoffAlgorithm()
        );
    }





    // configuration methods - jitter

    /**
     * Specify that full jitter should be used.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function fullJitter(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = new FullJitter();

        return $this;
    }

    /**
     * Specify that equal jitter should be used.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function equalJitter(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = new EqualJitter();

        return $this;
    }

    /**
     * Specify that a custom jitter range should be used.
     *
     * @param integer|float $min The jitter starting point, expressed as a percentage of the delay. e.g. 0.75 for 75%.
     * @param integer|float $max The jitter end point, expressed as a percentage of the delay. e.g. 1.25 for 125%.
     * @return $this
     * @throws BackoffInitialisationException Thrown when the min is greater than the max.
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function jitterRange(int|float $min, int|float $max): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = new RangeJitter($min, $max);

        return $this;
    }

    /**
     * Specify a callback that will apply the jitter.
     *
     * @param callable $callback The callback that will apply the jitter to use.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function jitterCallback(callable $callback): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = new CallbackJitter($callback);

        return $this;
    }

    /**
     * Specify the jitter instance to use.
     *
     * @param JitterInterface|null $jitter The jitter to apply.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function customJitter(?JitterInterface $jitter): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = $jitter;

        return $this;
    }

    /**
     * Specify that jitter should not be used.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noJitter(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->jitter = null;

        return $this;
    }





    // configuration methods - max attempts

    /**
     * Specify the maximum number of attempts to make.
     *
     * @param integer|null $maxAttempts The maximum number of attempts to make (null for no limit).
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function maxAttempts(?int $maxAttempts): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->maxAttempts = $maxAttempts;
        $this->reassessInitialStoppedState();

        return $this;
    }

    /**
     * Specify that no maximum limit should be applied to the number of attempts to make.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noMaxAttempts(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->maxAttempts = null;
        $this->reassessInitialStoppedState();

        return $this;
    }





    // configuration methods - max-delay

    /**
     * Specify the maximum delay to allow.
     *
     * @param integer|float|null $maxDelay The maximum delay to allow.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function maxDelay(int|float|null $maxDelay): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->maxDelay = $maxDelay;

        return $this;
    }

    /**
     * Specify that no maximum should be applied to the delay.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noMaxDelay(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->maxDelay = null;

        return $this;
    }





    // configuration methods - units of measurement

    /**
     * Specify the unit-of-measure.
     *
     * @param string $unit The unit-of-measure to use - from Settings::UNIT_XXX.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function unit(string $unit): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->unitType = $unit;

        return $this;
    }

    /**
     * Specify that the unit-of-measure should be seconds.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function unitSeconds(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->unitType = Settings::UNIT_SECONDS;

        return $this;
    }

    /**
     * Specify that the unit-of-measure should be milliseconds.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function unitMs(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->unitType = Settings::UNIT_MILLISECONDS;

        return $this;
    }

    /**
     * Specify that the unit-of-measure should be microseconds.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function unitUs(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->unitType = Settings::UNIT_MICROSECONDS;

        return $this;
    }





    // configuration methods - first attempt

    /**
     * Start the sequence with the first ATTEMPT, meaning no delay is applied the first iteration.
     *
     * @param boolean $before Whether the backoff handler should start with the first attempt, meaning no initial delay.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function runsBeforeFirstAttempt(bool $before = true): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->runsBeforeFirstAttempt = $before;

        return $this;
    }

    /**
     * Start the sequence with the first DELAY, meaning the normal delay is applied the first iteration.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function runsAfterFirstAttempt(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->runsBeforeFirstAttempt = false;

        return $this;
    }





    // configuration methods - insert an immediate retry

    /**
     * Insert an immediate retry (0 delay) for the first retry delay. Will be applied before the normal backoff.
     *
     * @param boolean $insert Whether to insert an immediate retry (0 delay) as the first retry delay.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function immediateFirstRetry(bool $insert = true): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->immediateFirstRetry = $insert;

        return $this;
    }

    /**
     * Don't insert an immediate retry (0 delay) for the first retry delay.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noImmediateFirstRetry(): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->immediateFirstRetry = false;

        return $this;
    }





    // configuration methods - disabling delays / retries

    /**
     * Enable or disable the backoff delays - when disabled, retries will occur immediately.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function onlyDelayWhen(bool $condition): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->delaysEnabled = $condition;

        return $this;
    }

    /**
     * Enable or disable the retries.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function onlyRetryWhen(bool $condition): self
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->retriesEnabled = $condition;

        return $this;
    }
}
