<?php

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Exceptions\BackoffInitialisationException;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\CallbackJitter;
use CodeDistortion\Backoff\Jitter\EqualJitter;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Algorithms\CallbackBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\DecorrelatedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\ExponentialBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FibonacciBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\NoopBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\PolynomialBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\RandomBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\SequenceBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;
use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\JitterInterface;

/**
 * Class that implements a backoff strategy.
 *
 * The parent class is Support/BaseBackoffStrategy implements the main logic.
 *
 * This class adds methods to assist with instantiation and configuration.
 */
class BackoffStrategy extends BaseBackoffStrategy
{
    /** @var integer The default maximum number of attempts to make. */
    public static int $defaultMaxAttempts = 5;



    // instantiation methods

    /**
     * Alternative constructor.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm       The backoff algorithm to use.
     * @param JitterInterface|null      $jitter                 The jitter to apply (default: no jitter).
     * @param integer|null              $maxAttempts            The maximum number of attempts to allow - null for
     *                                                          infinite (default: null).
     * @param integer|float|null        $maxDelay               The maximum delay to allow (optional).
     * @param string|null               $unitType               The unit type to use
     *                                                          (from Settings::UNIT_XXX, default: seconds).
     * @param boolean                   $runsBeforeFirstAttempt Whether the backoff strategy should start with the first
     *                                                          attempt, meaning no initial delay.
     * @param boolean                   $immediateFirstRetry    Whether to insert a 0 delay as the first retry delay.
     * @param boolean                   $delaysEnabled          Whether delays are allowed or not.
     * @param boolean                   $retriesEnabled         Whether retries are allowed or not.
     * @return self
     * @throws BackoffInitialisationException When an invalid $unitType is specified.
     */
    public static function new(
        BackoffAlgorithmInterface $backoffAlgorithm,
        ?JitterInterface $jitter = null,
        ?int $maxAttempts = null,
        int|float|null $maxDelay = null,
        ?string $unitType = Settings::UNIT_SECONDS,
        bool $runsBeforeFirstAttempt = false,
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
            $runsBeforeFirstAttempt,
            $immediateFirstRetry,
            $delaysEnabled,
            $retriesEnabled,
        );
    }





    // instantiation methods - for each backoff algorithm

    /**
     * Create a new backoff strategy using the fixed backoff algorithm.
     *
     * @param integer|float $delay The delay to use.
     * @return static
     */
    public static function fixed(int|float $delay): static
    {
        $algorithm = new FixedBackoffAlgorithm($delay);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the fixed backoff algorithm, in milliseconds.
     *
     * @param integer $delay The delay to use.
     * @return static
     */
    public static function fixedMs(int $delay): static
    {
        return static::fixed($delay)->unitMs();
    }

    /**
     * Create a new backoff strategy using the fixed backoff algorithm, in microseconds.
     *
     * @param integer $delay The delay to use.
     * @return static
     */
    public static function fixedUs(int $delay): static
    {
        return static::fixed($delay)->unitUs();
    }



    /**
     * Create a new backoff strategy using the linear backoff algorithm.
     *
     * @param integer|float      $initialDelay  The initial delay to use.
     * @param integer|float|null $delayIncrease The amount to increase the delay by (optional, falls back to
     *                                          $initialDelay).
     * @return static
     */
    public static function linear(int|float $initialDelay, int|float|null $delayIncrease = null): static
    {
        $algorithm = new LinearBackoffAlgorithm($initialDelay, $delayIncrease);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the linear backoff algorithm, in milliseconds.
     *
     * @param integer      $initialDelay  The initial delay to use.
     * @param integer|null $delayIncrease The amount to increase the delay by (optional, falls back to $initialDelay).
     * @return static
     */
    public static function linearMs(int $initialDelay, ?int $delayIncrease = null): static
    {
        return static::linear($initialDelay, $delayIncrease)->unitMs();
    }

    /**
     * Create a new backoff strategy using the linear backoff algorithm, in microseconds.
     *
     * @param integer      $initialDelay  The initial delay to use.
     * @param integer|null $delayIncrease The amount to increase the delay by (optional, falls back to $initialDelay).
     * @return static
     */
    public static function linearUs(int $initialDelay, ?int $delayIncrease = null): static
    {
        return static::linear($initialDelay, $delayIncrease)->unitUs();
    }



    /**
     * Create a new backoff strategy using the exponential backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param integer|float $factor       The factor to multiply by each time (default 2).
     * @return static
     */
    public static function exponential(int|float $initialDelay, int|float $factor = 2): static
    {
        $algorithm = new ExponentialBackoffAlgorithm($initialDelay, $factor);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the exponential backoff algorithm, in milliseconds.
     *
     * @param integer       $initialDelay The initial delay to use.
     * @param integer|float $factor       The factor to multiply by each time (default 2).
     * @return static
     */
    public static function exponentialMs(int $initialDelay, int|float $factor = 2): static
    {
        return static::exponential($initialDelay, $factor)->unitMs();
    }

    /**
     * Create a new backoff strategy using the exponential backoff algorithm, in microseconds.
     *
     * @param integer       $initialDelay The initial delay to use.
     * @param integer|float $factor       The factor to multiply by each time (default 2).
     * @return static
     */
    public static function exponentialUs(int $initialDelay, int|float $factor = 2): static
    {
        return static::exponential($initialDelay, $factor)->unitUs();
    }



    /**
     * Create a new backoff strategy using the polynomial backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param integer|float $power        The power to raise the retry number to (default 2).
     * @return static
     */
    public static function polynomial(int|float $initialDelay, int|float $power = 2): static
    {
        $algorithm = new PolynomialBackoffAlgorithm($initialDelay, $power);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the polynomial backoff algorithm, in milliseconds.
     *
     * @param integer       $initialDelay The initial delay to use.
     * @param integer|float $power        The power to raise the retry number to (default 2).
     * @return static
     */
    public static function polynomialMs(int $initialDelay, int|float $power = 2): static
    {
        return static::polynomial($initialDelay, $power)->unitMs();
    }

    /**
     * Create a new backoff strategy using the polynomial backoff algorithm, in microseconds.
     *
     * @param integer       $initialDelay The initial delay to use.
     * @param integer|float $power        The power to raise the retry number to (default 2).
     * @return static
     */
    public static function polynomialUs(int $initialDelay, int|float $power = 2): static
    {
        return static::polynomial($initialDelay, $power)->unitUs();
    }



    /**
     * Create a new backoff strategy using the fibonacci backoff algorithm.
     *
     * @param integer|float $initialDelay The initial delay to use.
     * @param boolean       $includeFirst Whether to include the first value in the Fibonacci sequence or not.
     * @return static
     */
    public static function fibonacci(int|float $initialDelay, bool $includeFirst = false): static
    {
        $algorithm = new FibonacciBackoffAlgorithm($initialDelay, $includeFirst);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the fibonacci backoff algorithm, in milliseconds.
     *
     * @param integer $initialDelay The initial delay to use.
     * @param boolean $includeFirst Whether to include the first value in the Fibonacci sequence or not.
     * @return static
     */
    public static function fibonacciMs(int $initialDelay, bool $includeFirst = false): static
    {
        return static::fibonacci($initialDelay, $includeFirst)->unitMs();
    }

    /**
     * Create a new backoff strategy using the fibonacci backoff algorithm, in microseconds.
     *
     * @param integer $initialDelay The initial delay to use.
     * @param boolean $includeFirst Whether to include the first value in the Fibonacci sequence or not.
     * @return static
     */
    public static function fibonacciUs(int $initialDelay, bool $includeFirst = false): static
    {
        return static::fibonacci($initialDelay, $includeFirst)->unitUs();
    }



    /**
     * Create a new backoff strategy using the decorrelated backoff algorithm.
     *
     * @param integer|float $baseDelay  The base delay to use.
     * @param integer|float $multiplier The amount to multiply the previous delay by (default 3).
     * @return static
     */
    public static function decorrelated(int|float $baseDelay, int|float $multiplier = 3): static
    {
        $algorithm = new DecorrelatedBackoffAlgorithm($baseDelay, $multiplier);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the decorrelated backoff algorithm, in milliseconds.
     *
     * @param integer       $baseDelay  The base delay to use.
     * @param integer|float $multiplier The amount to multiply the previous delay by (default 3).
     * @return static
     */
    public static function decorrelatedMs(int $baseDelay, int|float $multiplier = 3): static
    {
        return static::decorrelated($baseDelay, $multiplier)->unitMs();
    }

    /**
     * Create a new backoff strategy using the decorrelated backoff algorithm, in microseconds.
     *
     * @param integer       $baseDelay  The base delay to use.
     * @param integer|float $multiplier The amount to multiply the previous delay by (default 3).
     * @return static
     */
    public static function decorrelatedUs(int $baseDelay, int|float $multiplier = 3): static
    {
        return static::decorrelated($baseDelay, $multiplier)->unitUs();
    }



    /**
     * Create a new backoff strategy using the random backoff algorithm.
     *
     * @param integer|float $minDelay The minimum delay to use.
     * @param integer|float $maxDelay The maximum delay to use.
     * @throws BackoffInitialisationException Thrown when the minDelay is greater than the maxDelay.
     * @return static
     */
    public static function random(int|float $minDelay, int|float $maxDelay): static
    {
        $algorithm = new RandomBackoffAlgorithm($minDelay, $maxDelay);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the random backoff algorithm, in milliseconds.
     *
     * @param integer $minDelay The minimum delay to use.
     * @param integer $maxDelay The maximum delay to use.
     * @throws BackoffInitialisationException Thrown when the minDelay is greater than the maxDelay.
     * @return static
     */
    public static function randomMs(int $minDelay, int $maxDelay): static
    {
        return static::random($minDelay, $maxDelay)->unitMs();
    }

    /**
     * Create a new backoff strategy using the random backoff algorithm, in microseconds.
     *
     * @param integer $minDelay The minimum delay to use.
     * @param integer $maxDelay The maximum delay to use.
     * @throws BackoffInitialisationException Thrown when the minDelay is greater than the maxDelay.
     * @return static
     */
    public static function randomUs(int $minDelay, int $maxDelay): static
    {
        return static::random($minDelay, $maxDelay)->unitUs();
    }



    /**
     * Create a new backoff strategy using the sequence backoff algorithm.
     *
     * @param array<integer|float> $delays The sequence of delays to use.
     * @return static
     */
    public static function sequence(array $delays): static
    {
        $algorithm = new SequenceBackoffAlgorithm($delays);
        return static::new($algorithm);
    }

    /**
     * Create a new backoff strategy using the sequence backoff algorithm, in milliseconds.
     *
     * @param array<integer> $delays The sequence of delays to use.
     * @return static
     */
    public static function sequenceMs(array $delays): static
    {
        return static::sequence($delays)->unitMs();
    }

    /**
     * Create a new backoff strategy using the sequence backoff algorithm, in microseconds.
     *
     * @param array<integer> $delays The sequence of delays to use.
     * @return static
     */
    public static function sequenceUs(array $delays): static
    {
        return static::sequence($delays)->unitUs();
    }



    /**
     * Create a new backoff strategy using the callback backoff algorithm.
     *
     * @param callable $callback The callback that will determine the delays to use.
     * @return static
     */
    public static function callback(callable $callback): static
    {
        $algorithm = new CallbackBackoffAlgorithm($callback);
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using the callback backoff algorithm, in milliseconds.
     *
     * @param callable $callback The callback that will determine the delays to use.
     * @return static
     */
    public static function callbackMs(callable $callback): static
    {
        return static::callback($callback)->unitMs();
    }

    /**
     * Create a new backoff strategy using the callback backoff algorithm, in microseconds.
     *
     * @param callable $callback The callback that will determine the delays to use.
     * @return static
     */
    public static function callbackUs(callable $callback): static
    {
        return static::callback($callback)->unitUs();
    }



    /**
     * Create a new backoff strategy using a custom backoff algorithm.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm The backoff algorithm instance to use.
     * @return static
     */
    public static function custom(BackoffAlgorithmInterface $backoffAlgorithm): static
    {
        return static::new($backoffAlgorithm)->maxAttempts(self::$defaultMaxAttempts);
    }

    /**
     * Create a new backoff strategy using a custom backoff algorithm, in milliseconds.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm The backoff algorithm instance to use.
     * @return static
     */
    public static function customMs(BackoffAlgorithmInterface $backoffAlgorithm): static
    {
        return static::custom($backoffAlgorithm)->unitMs();
    }

    /**
     * Create a new backoff strategy using a custom backoff algorithm, in microseconds.
     *
     * @param BackoffAlgorithmInterface $backoffAlgorithm The backoff algorithm instance to use.
     * @return static
     */
    public static function customUs(BackoffAlgorithmInterface $backoffAlgorithm): static
    {
        return static::custom($backoffAlgorithm)->unitUs();
    }



    /**
     * Create a new backoff strategy using the "noop" backoff algorithm.
     *
     * @return static
     */
    public static function noop(): static
    {
        $algorithm = new NoopBackoffAlgorithm();
        return static::new($algorithm)->maxAttempts(self::$defaultMaxAttempts);
    }



    /**
     * Create a new backoff strategy using the "no backoff" algorithm.
     *
     * @return static
     */
    public static function none(): static
    {
        $algorithm = new NoBackoffAlgorithm();
        // add maxAttempts just for good measure seeing it's known anyway
        return static::new($algorithm)->maxAttempts(1);
    }





    // configuration methods - jitter

    /**
     * Specify that full jitter should be used.
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function fullJitter(): static
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
    public function equalJitter(): static
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
    public function jitterRange(int|float $min, int|float $max): static
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
    public function jitterCallback(callable $callback): static
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
    public function customJitter(?JitterInterface $jitter): static
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
    public function noJitter(): static
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
    public function maxAttempts(?int $maxAttempts): static
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
     * todo - rename to (or add) unlimitedAttempts() ???
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noMaxAttempts(): static
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
    public function maxDelay(int|float|null $maxDelay): static
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
     * todo - rename to (or add) unlimitedDelay() ???
     *
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function noMaxDelay(): static
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
    public function unit(string $unit): static
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
    public function unitSeconds(): static
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
    public function unitMs(): static
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
    public function unitUs(): static
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
     * @param boolean $before Whether the backoff strategy should start with the first attempt, meaning no initial
     *                        delay.
     * @return $this
     * @throws BackoffRuntimeException When the backoff process has already started.
     */
    public function runsBeforeFirstAttempt(bool $before = true): static
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
    public function runsAfterFirstAttempt(): static
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
    public function immediateFirstRetry(bool $insert = true): static
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
    public function noImmediateFirstRetry(): static
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
    public function onlyDelayWhen(bool $condition): static
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
    public function onlyRetryWhen(bool $condition): static
    {
        if ($this->hasStarted()) {
            throw BackoffRuntimeException::attemptToChangeAfterStart(__FUNCTION__);
        }

        $this->retriesEnabled = $condition;

        return $this;
    }
}
