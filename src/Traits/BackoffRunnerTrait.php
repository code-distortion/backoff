<?php

namespace CodeDistortion\Backoff\Traits;

use CodeDistortion\Backoff\Support\PossibleMatch;
use CodeDistortion\Backoff\Support\Support;
use Throwable;

/**
 * Adds backoff-strategy runner functionality.
 */
trait BackoffRunnerTrait
{
    use BackoffStrategyTrait;



    /** @var PossibleMatch[]|false The class-string exceptions to retry, or callback to use to get the result. */
    private array|false $retryExceptions = [];

    /** @var callable[] The callbacks to call when an exception occurs. */
    private array $exceptionCallbacks = [];



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
        if ($exceptions === false) {
            $this->retryExceptions = false;
            return $this;
        }

        $defaultWasSpecified = (func_num_args() >= 2);

        $exceptions = Support::normaliseParameters([$exceptions], true);

        // specifying no exceptions means to catch *all* exceptions
        // (this overrides previously set exceptions)
        if ($exceptions == []) {
            $this->retryExceptions = [];
            // true, so -any- exception is matched
            $this->retryExceptions[] = new PossibleMatch(true, $defaultWasSpecified, $default);
        } else {

            $this->retryExceptions = is_array($this->retryExceptions)
                ? $this->retryExceptions
                : [];

            foreach ($exceptions as $exception) {
                $this->retryExceptions[] = new PossibleMatch($exception, $defaultWasSpecified, $default);
            }
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
     * @return $this
     */
    public function dontRetryExceptions(): static
    {
        $this->retryExceptions = false;

        return $this;
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

            return $this->performAttempt($callback, $default, $defaultWasSpecifiedByCaller);

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
     * @param mixed    $default                     The default value to return if all attempts fail.
     * @param boolean  $defaultWasSpecifiedByCaller Whether the default value was specified by the caller or not.
     * @return mixed
     * @throws Throwable When the last exception should be rethrown.
     */
    private function performAttempt(callable $callback, mixed $default, bool $defaultWasSpecifiedByCaller): mixed
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

                if (count($this->retryWhenResult)) {

                    // check to make sure the $result doesn't match anything from $this->retryWhenResult
                    if ($invalidResultMatch = $this->pickMatchingResult($result, $this->retryWhenResult)) {
                        $wasSuccessful = false;
                        $overrideDefault = $invalidResultMatch->hasDefault();
                        $overrideDefaultWith = $invalidResultMatch->getDefault();
                    }
                }

                if (count($this->retryUntilResult)) {

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
                    $this->callInvalidResultCallbacks($result);
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
                if ($exceptionMatch) {
                    $overrideDefault = $exceptionMatch->hasDefault();
                    $overrideDefaultWith = $exceptionMatch->getDefault();
                } else {
                    $stop = true;
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

        if ($invalidResultCallbackException) {
            throw $invalidResultCallbackException;
        }

        if ($overrideDefault) {
            return $this->resolveDefaultValue($overrideDefaultWith);
        }

        if ($defaultWasSpecifiedByCaller) {
            return $this->resolveDefaultValue($default);
        }

        if ($attemptException) {
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

        // when not initialised by the caller, catch -any- exception (default behaviour)
        if (!count($this->retryExceptions)) {
            return new PossibleMatch(true);
        }

        // the user specified particular exceptions to catch, or callback/s to check with
        foreach ($this->retryExceptions as $possibleMatch) {

            // true to match -any- exception
            if ($possibleMatch->value === true) {
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

        // no exceptions matched
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
     * @param mixed $result The result that was returned.
     * @return void
     */
    private function callInvalidResultCallbacks(mixed $result): void
    {
        foreach ($this->invalidResultCallbacks as $callback) {
            $callback($result, $this->currentLog());
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







//    /**
//     * Call a callback provided it accepts a Throwable of the same type as the exception passed.
//     *
//     * @param callable  $callable  The callback to call.
//     * @param Throwable $exception The exception to pass to the callback.
//     * @return mixed
//     * @throws BackoffRuntimeException When the callback's expects more than 1 parameter.
//     * @throws BackoffRuntimeException When the callback's parameter can't be resolved.
//     */
//    private function callExceptionCallback(callable $callable, Throwable $exception): mixed
//    {
//        return $callable($exception, $this->currentLog());
//
//
//
//        // todo - remove below
//
//        // make sure the callable accepts 0 or 1 parameter
//        $parameters = $this->generateCallbackParameters($callable, $exception);
//        if (count($parameters) > 1) {
//            throw BackoffRuntimeException::exceptionCallbackAcceptsMoreThan1Param(array_keys($parameters));
//        }
//
//        // make sure the exception was matched to a parameter before calling it, otherwise, skip it
//        if (in_array(null, $parameters, true)) {
//            return null;
//        }
//
//        // todo - add $this->currentLog() to the parameters
//        // todo - allow for this to call a callback when the parameter doesn't specify a type
//        return $callable(...$parameters);
//    }
//
//    /**
//     * Generate the parameters to pass to a callback.
//     *
//     * @param callable  $callable  The callback that will be called.
//     * @param Throwable $exception The exception that needs to be passed to the callback.
//     * @return mixed[]
//     * @throws BackoffRuntimeException When the callback's parameters can't be resolved.
//     */
//    private function generateCallbackParameters(callable $callable, Throwable $exception): array
//    {
//        $return = [];
//        $reflection = $this->getCallableReflection($callable);
//        foreach ($reflection->getParameters() as $parameter) {
//            $parameterName = $parameter->getName();
//            $return[$parameterName] = $this->exceptionIfParameterMatches($parameter, $exception);
//        }
//
//        return $return;
//    }

//    /**
//     * Get the reflection method/function for a callable.
//     *
//     * @param callable $callable The callable to get the reflection method for.
//     * @return ReflectionMethod|ReflectionFunction
//     * @throws ReflectionException When the callable can't be used.
//     */
//    private function getCallableReflection(callable $callable): ReflectionMethod|ReflectionFunction
//    {
//        // determine if the callable is a function or a method
//
//        // callable is an array, could be a static or instance method
//        if (is_array($callable)) {
//
//            [$objectOrClass, $method] = $callable;
//            return new ReflectionMethod($objectOrClass, $method);
//        }
//
//        // callable is a Closure or a function name
//        if ($callable instanceof Closure || is_string($callable)) {
//            return new ReflectionFunction($callable);
//        }
//
//        // callable is an invokable object
//        return new ReflectionMethod($callable, '__invoke');
//    }

//    /**
//     * Check a parameter to see if it matches the exception.
//     *
//     * @param ReflectionParameter $parameter The parameter to check.
//     * @param Throwable           $exception The exception to check against.
//     * @return Throwable|null
//     * @throws BackoffRuntimeException When the parameter cannot be used.
//     */
//    private function exceptionIfParameterMatches(ReflectionParameter $parameter, Throwable $exception): ?Throwable
//    {
//        $parameterName = $parameter->getName();
//        $parameterType = $parameter->getType();
//
//        // a parameter might have one type,
//        // or several in the case of union and intersection types,
//        // (or none when not specified)
//        // collect them all into an array so they can all be checked (only one needs to match)
//        $types = [];
//        $typeHintJoinType = null;
//        if ($parameterType instanceof ReflectionNamedType) {
//            $types[] = $parameterType;
//        } elseif ($parameterType instanceof ReflectionUnionType) {
//            $typeHintJoinType = '|';
//            $types = $parameterType->getTypes();
//        } elseif ($parameterType instanceof ReflectionIntersectionType) {
//            $typeHintJoinType = '&';
//            $types = $parameterType->getTypes();
//        } else {
//            throw BackoffRuntimeException::invalidExceptionCallbackParameter($parameterName);
//        }
//
//        $foundExceptionType = false;
//        $typeHintParts = [];
//        /** @var ReflectionNamedType $type */
//        foreach ($types as $type) {
//
//            $typeHintParts[] = $type->getName();
//
//            // ignore built-in types - int, float, etc
//            if ($type->isBuiltin()) {
//                continue;
//            }
//
//            $className = $type->getName();
//            if (!$this->paramIsAnExceptionOfSomeSort($className)) {
//                continue;
//            }
//            $foundExceptionType = true;
//
//            if ($this->isA($className, $exception)) {
//                return $exception;
//            }
//        }
//
//        $typeHint = $typeHintJoinType
//            ? implode($typeHintJoinType, $typeHintParts)
//            : $typeHintParts[0] ?? null;
//
//        return $foundExceptionType
//            ? null
//            : throw BackoffRuntimeException::invalidExceptionCallbackParameter($parameterName, $typeHint);
//    }

//    /**
//     * Check if a class is a Throwable or a subclass of Throwable.
//     *
//     * @param string $class The class to check.
//     * @return boolean
//     */
//    private function paramIsAnExceptionOfSomeSort(string $class): bool
//    {
//        return (($class === 'Throwable') || (is_subclass_of($class, 'Throwable')));
//    }

//    /**
//     * Check if a class is a particular type of exception.
//     *
//     * @param string    $class     The class to check.
//     * @param Throwable $exception The exception to check against.
//     * @return boolean
//     */
//    private function isA(string $class, Throwable $exception): bool
//    {
//        if ($class == get_class($exception)) {
//            return true;
//        }
//
//        if (is_subclass_of($exception, $class)) {
//            return true;
//        }
//
//        return false;
//    }
}
