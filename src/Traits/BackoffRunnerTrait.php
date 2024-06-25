<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Traits;

use CodeDistortion\Backoff\Support\PossibleMatch;
use CodeDistortion\Backoff\Support\Support;
use Throwable;

/**
 * Adds backoff-strategy runner functionality to a class.
 * - tries the action,
 * - retries when:
 *   - exceptions occur, and/or
 *   - certain exceptions occur, and/or
 *   - a matching result is returned, and/or
 *   - a matching result is not returned
 * - uses the backoff-strategy to manage the retry sleeps, and when to abandon,
 * - calls callbacks at certain stages,
 * - returns the result, a default value, or re-throws the last exception.
 *
 * @see BackoffStrategyTrait
 */
trait BackoffRunnerTrait
{
    use BackoffStrategyTrait;



    /** @var PossibleMatch[]|false The class-string exceptions to retry, or callback to use to get the result. */
    private array|false $retryExceptions = [];

    /** @var callable[] The callbacks to call when an exception occurs. */
    private array $exceptionCallbacks = [];

    /** @var boolean Whether a default value has been provided when not catching exceptions. */
    private bool $hasExceptionDefault = false;

    /** @var mixed The default value to return when not catching exceptions, instead of rethrowing. */
    private mixed $exceptionDefault = null;



    /** @var PossibleMatch[] The values that indicate the result is a failure, and a retry should happen. */
    private array $retryWhenResult = [];

    /** @var PossibleMatch[] The values that indicate the result is successful, and no more retries should happen. */
    private array $retryUntilResult = [];

    /** @var callable[] The callbacks to call when an invalid result value is returned. */
    private array $invalidResultCallbacks = [];



    /** @var callable[] The callbacks to call when the action was successful. */
    private array $successCallbacks = [];

    /** @var callable[] The callbacks to call when all attempts fail. */
    private array $failureCallbacks = [];

    /** @var callable[] The callbacks to call when the process finishes, regardless of the outcome. */
    private array $finallyCallbacks = [];









    /**
     * Specify the exceptions to retry - one or more (or an array of) exception class-strings, or callbacks to call
     * when they occur to work out the answer.
     *
     * Passing no exceptions means to catch all exceptions, and will override any previously set exceptions.
     *
     * @param class-string|callable|array<class-string|callable> $exceptions The exceptions to retry.
     * @param mixed                                              $default    The default value to return if all attempts
     *                                                                       fail.
     * @return $this
     */
    public function retryExceptions(string|callable|array|false $exceptions = [], mixed $default = null): static
    {
        $defaultWasSpecified = (func_num_args() >= 2);

        if ($exceptions === false) {
            $this->retryExceptions = false;
            $this->hasExceptionDefault = $defaultWasSpecified;
            $this->exceptionDefault = $default;
            return $this;
        }

        $this->hasExceptionDefault = false;
        $this->exceptionDefault = null;

        if (!is_array($this->retryExceptions)) {
            $this->retryExceptions = [];
        }

        $exceptions = Support::normaliseParameters([$exceptions], true);
        $exceptions = ($exceptions === [])
            ? [true] // retry all exceptions
            : $exceptions;

        foreach ($exceptions as $exception) {
            $this->retryExceptions[] = new PossibleMatch($exception, $defaultWasSpecified, $default);
        }

        return $this;
    }

    /**
     * Retry when any exception occurs.
     *
     * @param mixed $default The default value to return if all attempts fail (when omitted, the exception is rethrown).
     * @return $this
     */
    public function retryAllExceptions(mixed $default = null): static
    {
        $defaultWasSpecified = (func_num_args() >= 1);

        return $defaultWasSpecified
            ? $this->retryExceptions([], $default)
            : $this->retryExceptions([]);
    }

    /**
     * Specify that exceptions should not be retried.
     *
     * @param mixed $default The default value to return if an exception occurs (when omitted, the exception is
     *                       rethrown).
     * @return $this
     */
    public function dontRetryExceptions(mixed $default = null): static
    {
        $defaultWasSpecified = (func_num_args() >= 1);

        return $defaultWasSpecified
            ? $this->retryExceptions(false, $default)
            : $this->retryExceptions(false);
    }

    /**
     * Specify callback/s to call when an exception occurs.
     *
     * $callback(Throwable $e, AttemptLog $log, bool $willRetry): void.
     *
     * @param callable|callable[] $callback     The callback to call when an exception occurs.
     * @param callable|callable[] ...$callbacks Further callback/s to call when an exception occurs.
     * @return $this
     */
    public function exceptionCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->exceptionCallbacks = array_merge($this->exceptionCallbacks, $callbacks);

        return $this;
    }





    /**
     * Specify that the result fails (and the action should be retried) when it matches the given value.
     *
     * @param mixed|callable $match   The value to check for.
     * @param boolean        $strict  Whether to use strict comparison (default false).
     * @param mixed          $default The default value to return if all attempts fail.
     * @return $this
     */
    public function retryWhen(mixed $match, bool $strict = false, mixed $default = null): static
    {
        $defaultWasSpecified = (func_num_args() >= 3);
        $this->retryWhenResult[] = new PossibleMatch($match, $defaultWasSpecified, $default, $strict);

        $this->retryUntilResult = []; // reset ->retryUntil()

        return $this;
    }

    /**
     * Specify that the result is successful (and retries should stop) when it matches the given value.
     *
     * @param mixed|callable $match  The value to check for.
     * @param boolean        $strict Whether to use strict comparison (default false).
     * @return $this
     */
    public function retryUntil(mixed $match, bool $strict = false): static
    {
        $this->retryWhenResult = []; // reset ->retryWhen()

        $this->retryUntilResult[] = new PossibleMatch($match, strict: $strict);

        return $this;
    }

    /**
     * Specify callback/s to call when the result is considered invalid.
     *
     * $callback(mixed $result, AttemptLog $log): void.
     *
     * @see retryWhen()
     * @see retryUntil()
     *
     * @param callable|callable[] $callback     The callback to call when an exception occurs.
     * @param callable|callable[] ...$callbacks Further callback/s to call when an exception occurs.
     * @return $this
     */
    public function invalidResultCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->invalidResultCallbacks = array_merge($this->invalidResultCallbacks, $callbacks);

        return $this;
    }





    /**
     * Specify callback/s to call when the action was successful.
     *
     * $callback(AttemptLog[] $logs): void.
     *
     * @param callable|callable[] $callback     The callback to call.
     * @param callable|callable[] ...$callbacks Further callback/s to call.
     * @return $this
     */
    public function successCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->successCallbacks = array_merge($this->successCallbacks, $callbacks);

        return $this;
    }

    /**
     * Specify callback/s to call when the all attempts fail.
     *
     * $callback(AttemptLog[] $logs): void.
     *
     * @param callable|callable[] $callback     The callback to call.
     * @param callable|callable[] ...$callbacks Further callback/s to call.
     * @return $this
     */
    public function failureCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->failureCallbacks = array_merge($this->failureCallbacks, $callbacks);

        return $this;
    }

    /**
     * Specify callback/s to call when the all attempts fail.
     *
     * $callback(AttemptLog[] $logs): void.
     *
     * Alias for failureCallback.
     * @see failureCallback()
     *
     * @param callable|callable[] $callback     The callback to call.
     * @param callable|callable[] ...$callbacks Further callback/s to call.
     * @return $this
     */
    public function fallbackCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        return $this->failureCallback($callback, ...$callbacks);
    }

    /**
     * Specify callback/s to call at the end of the process, regardless of the outcome.
     *
     * $callback(AttemptLog[] $logs): void.
     *
     * @param callable|callable[] $callback     The callback to call.
     * @param callable|callable[] ...$callbacks Further callback/s to call.
     * @return $this
     */
    public function finallyCallback(callable|array $callback, callable|array ...$callbacks): static
    {
        /** @var callable[] $callbacks */
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->finallyCallbacks = array_merge($this->finallyCallbacks, $callbacks);

        return $this;
    }





    /**
     * Run the callback and apply the backoff strategy when it fails.
     *
     * @param callable $callback The callback to run.
     * @param mixed    $default  The default value to return if all attempts fail.
     * @return mixed
     * @throws Throwable When the last exception should be rethrown.
     */
    public function attempt(callable $callback, mixed $default = null): mixed
    {
        $defaultWasSpecifiedByCaller = (func_num_args() >= 2);

        // $this->strategy->runsAtStartOfLoop()
        $origRunsAtStartOfLoop = $this->runsAtStartOfLoop; // so this setting can be restored afterwards

        // $this->strategy->reset()->runsAtStartOfLoop()
        $this->reset()->runsAtStartOfLoop();

        try {

            return $this->performAttempt($callback, $defaultWasSpecifiedByCaller, $default);

        } finally {
            // reset, and return certain values to the original state
            // $this->strategy->reset()->runsAtStartOfLoop()
            $this->reset()->runsAtStartOfLoop($origRunsAtStartOfLoop);
        }
    }

    /**
     * Actually perform the attempt process.
     *
     * @param callable $callback                    The callback to run.
     * @param boolean  $defaultWasSpecifiedByCaller Whether the default value was specified by the caller or not.
     * @param mixed    $default                     The default value to return if all attempts fail.
     * @return mixed
     * @throws Throwable When the last exception should be rethrown.
     */
    private function performAttempt(callable $callback, bool $defaultWasSpecifiedByCaller, mixed $default): mixed
    {
        $overrideDefault = false;
        $overrideDefaultWith = null;
        $result = null;

        $attemptException = null;
        $invalidResultCallbackException = null;

        // $this->strategy->step()
        while ($this->step()) {
            try {

                $overrideDefault = false;
                $overrideDefaultWith = null;

                $result = null;

                $this->startOfAttempt(); // record the time closest to the attempt actually starting
                $result = $callback();
                $this->endOfAttempt(); // record the time closest to the attempt actually finishing

                $wasSuccessful = true;

                if ((bool) count($this->retryWhenResult)) {

                    // check to make sure the $result doesn't match anything from $this->retryWhenResult
                    $invalidResultMatch = $this->pickMatchingResult($result, $this->retryWhenResult);
                    if (!is_null($invalidResultMatch)) {
                        $wasSuccessful = false;
                        $overrideDefault = $invalidResultMatch->hasDefault();
                        $overrideDefaultWith = $invalidResultMatch->getDefault();
                    }
                }

                if ((bool) count($this->retryUntilResult)) {

                    // or check to make sure the $result matches one of $this->isSuccessfulWhenResult
                    $validResultMatch = $this->pickMatchingResult($result, $this->retryUntilResult);
                    $wasSuccessful = !is_null($validResultMatch);
                }

                // if the result was successful, return it
                if ($wasSuccessful) {
                    $this->callSuccessCallbacks();
                    $this->callFinallyCallbacks();
                    return $result;
                }

                try {
                    $this->callInvalidResultCallbacks($result, !$this->isLastAttempt());
                } catch (Throwable $invalidResultCallbackException) {
                    // the exception is stored in $invalidResultCallbackException
                    // and re-thrown below, outside the main try/catch
                    break;
                }

            } catch (Throwable $attemptException) {

                $this->endOfAttempt();

                // check to see if the exception matched one in the list,
                // so we can ascertain if it specified a default value to return
                $stop = false;
                $exceptionMatch = $this->pickMatchingExceptionType($attemptException);
                if (!is_null($exceptionMatch)) {
                    $overrideDefault = $exceptionMatch->hasDefault();
                    $overrideDefaultWith = $exceptionMatch->getDefault();
                } else {
                    $stop = true;
                    $overrideDefault = $this->hasExceptionDefault;
                    $overrideDefaultWith = $this->exceptionDefault;
                }

                // or check if it's the last attempt
                // $this->strategy->isLastAttempt()
                $stop = $stop || $this->isLastAttempt();

                $this->callExceptionCallbacks($attemptException, !$stop);

                if ($stop) {
                    break;
                }
            }
        }

        $this->callFailureCallbacks();
        $this->callFinallyCallbacks();

        if (!is_null($invalidResultCallbackException)) {
            throw $invalidResultCallbackException;
        }

        if ($overrideDefault) {
            return $this->resolveDefaultValue($overrideDefaultWith);
        }

        if ($defaultWasSpecifiedByCaller) {
            return $this->resolveDefaultValue($default);
        }

        if (!is_null($attemptException)) {
            throw $attemptException;
        }

        return $result;
    }





    /**
     * Check if a result value matches the values that indicate a retry should (or shouldn't) happen.
     *
     * @param mixed           $result             The result to check.
     * @param PossibleMatch[] $matchPossibilities The values to check against.
     * @return PossibleMatch|null
     */
    private function pickMatchingResult(mixed $result, array $matchPossibilities): ?PossibleMatch
    {
        foreach ($matchPossibilities as $possibleMatch) {

            if (is_callable($possibleMatch->value)) {

                $callback = $possibleMatch->value;
                if ($callback($result, $this->currentLog())) {
                    return $possibleMatch;
                }

            } else {

                if (($possibleMatch->strict) && ($result === $possibleMatch->value)) {
                    return $possibleMatch;
                }

                if ((!$possibleMatch->strict) && ($result == $possibleMatch->value)) {
                    return $possibleMatch;
                }
            }
        }
        return null;
    }

    /**
     * Check if a retry is allowed based on the type of exception.
     *
     * @param Throwable $e The exception to check.
     * @return PossibleMatch|null
     */
    private function pickMatchingExceptionType(Throwable $e): ?PossibleMatch
    {
        // the user has specified to not catch any exceptions
        if ($this->retryExceptions === false) {
            return null;
        }

        // when not initialised by the caller, catch -any- exception, with no default (default behaviour)
        if (count($this->retryExceptions) == 0) {
            return new PossibleMatch(true);
        }

        // loop through the possible matches in order so that the most specific match is used. In this order:
        // - catch particular exception - with a default
        // - catch all exceptions - with a default
        // - catch particular exception - with NO default
        // - catch all exceptions - with NO default
        foreach ([true, false] as $hasDefault) {
            foreach ([false, true] as $matchingAllExceptions) {

                // check for the exceptions that have been specified
                // or call callbacks to check if the exception should be retried
                foreach ($this->retryExceptions as $possibleMatch) {

                    if ($possibleMatch->hasDefault() !== $hasDefault) {
                        continue;
                    }
                    $matchesAllExceptions = ($possibleMatch->value === true);
                    if ($matchesAllExceptions !== $matchingAllExceptions) {
                        continue;
                    }

                    if ($matchesAllExceptions) {
                        return $possibleMatch;
                    }

                    if (is_string($possibleMatch->value)) {
                        if ($e instanceof $possibleMatch->value) {
                            return $possibleMatch;
                        }
                    } elseif (is_callable($possibleMatch->value)) {

                        $callable = $possibleMatch->value;
                        if ($callable($e, $this->currentLog())) {
                            return $possibleMatch;
                        }
                    }
                }
            }
        }

        return null;
    }





    /**
     * Call the callbacks that should be called when an exception occurs.
     *
     * @param Throwable $e         The exception that occurred.
     * @param boolean   $willRetry Whether the action will be retried.
     * @return void
     */
    private function callExceptionCallbacks(Throwable $e, bool $willRetry): void
    {
        foreach ($this->exceptionCallbacks as $callback) {
            $callback($e, $this->currentLog(), $willRetry);
        }
    }

    /**
     * Call the callbacks that should be called when an invalid result is returned.
     *
     * @param mixed   $result    The result that was returned.
     * @param boolean $willRetry Whether the action will be retried.
     * @return void
     */
    private function callInvalidResultCallbacks(mixed $result, bool $willRetry): void
    {
        foreach ($this->invalidResultCallbacks as $callback) {
            $callback($result, $this->currentLog(), $willRetry);
        }
    }

    /**
     * Call the callbacks that should be called when the action completes successfully.
     *
     * @return void
     */
    private function callSuccessCallbacks(): void
    {
        foreach ($this->successCallbacks as $callback) {
            $callback($this->logs());
        }
    }

    /**
     * Call the callbacks that should be called when the action fails altogether.
     *
     * @return void
     */
    private function callFailureCallbacks(): void
    {
        foreach ($this->failureCallbacks as $callback) {
            $callback($this->logs());
        }
    }

    /**
     * Call the callbacks that should be called when the process finishes, regardless of the outcome.
     *
     * @return void
     */
    private function callFinallyCallbacks(): void
    {
        foreach ($this->finallyCallbacks as $callback) {
            $callback($this->logs());
        }
    }





    /**
     * Take the default value and return it (or call it if it's a callback and return the response).
     *
     * @param mixed $default The default value to use.
     * @return mixed
     */
    private function resolveDefaultValue(mixed $default): mixed
    {
        return is_callable($default)
            ? $default()
            : $default;
    }
}
