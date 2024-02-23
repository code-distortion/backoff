<?php

namespace CodeDistortion\Backoff;

use Closure;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Support\Support;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

/**
 * A class that takes a BackoffStrategy and runs the backoff process through to completion.
 */
class Backoff extends BackoffStrategy
{
    /** @var array<class-string|callable>|false The class-string exceptions to retry, or callback to use to get the result. */
    private array|false $retryExceptions = [];

    /** @var callable[] The callbacks to call when an exception occurs. */
    private array $failedExceptionCallbacks = [];

    /** @var boolean Whether the last exception should be rethrown or not. */
    private bool $rethrowLastException = true;



    /** @var callable[] The callbacks to call when a failed response value is returned. */
    private array $failedResultCallbacks = [];



    /**
     * Specify the exceptions to retry - one or more (or an array of) exception class-strings, or callbacks to call
     * when they occur to work out the answer.
     *
     * todo - test that passing no exceptions to this method resets this so that all exceptions are retried
     *
     * @param class-string|callable|array<class-string|callable> ...$exceptions The exceptions to retry.
     * @return $this
     */
    public function retryExceptions(string|callable|array ...$exceptions): self
    {
        $exceptions = Support::normaliseParameters($exceptions, true);

        // specifying no exceptions means to catch all exceptions
        // (this overrides previously set exceptions)
        if ($exceptions == []) {
            $this->retryExceptions = [];
        } else {
            if ($this->retryExceptions === false) {
                $this->retryExceptions = [];
            }
            $this->retryExceptions = array_merge($this->retryExceptions ??= [], $exceptions);
        }

        return $this;
    }

    /**
     * Specify that exceptions should not be retried.
     *
     * @return $this
     */
    public function dontRetryExceptions(): self
    {
        $this->retryExceptions = false;

        return $this;
    }

    /**
     * Specify that exceptions should be rethrown instead of retried.
     *
     * @return $this
     */
    public function rethrowExceptions(): self
    {
        $this->retryExceptions = false;

        return $this;
    }

    /**
     * Specify that the last exception should be rethrown - if not, the default value is returned.
     *
     * @param boolean $rethrow Whether the last exception should be rethrown or not (default true).
     * @return $this
     */
    public function rethrowLastException(bool $rethrow = true): self
    {
        $this->rethrowLastException = $rethrow;

        return $this;
    }

    /**
     * Specify that the last exception should not be rethrown - in which case the default value is returned.
     *
     * @return $this
     */
    public function dontRethrowLastException(): self
    {
        $this->rethrowLastException = false;

        return $this;
    }

    /**
     * Specify callback/s to call when an exception occurs.
     *
     * @param callable|callable[] $callback     The callback to call when an exception occurs.
     * @param callable|callable[] ...$callbacks Further callback/s to call when an exception occurs.
     * @return $this
     */
    public function callUponException(callable|array $callback, callable|array ...$callbacks): self
    {
        $callbacks = Support::normaliseParameters(array_merge([$callback], $callbacks), true);
        $this->failedExceptionCallbacks = array_merge($this->failedExceptionCallbacks, $callbacks);

        return $this;
    }





    /**
     * Specify that the result fails (and the action should be retried) when it matches the given value.
     *
     * @param mixed   $value  The value to check for.
     * @param boolean $strict Whether to use strict comparison (default false).
     * @return $this
     */
    public function retryWhenResult(mixed $value, bool $strict = false): self
    {
        // todo

        return $this;
    }

    /**
     * Specify that the result is successful (and retries should stop) when it matches the given value.
     *
     * @param mixed   $value  The value to check for.
     * @param boolean $strict Whether to use strict comparison (default false).
     * @return $this
     */
    public function isSuccessfulWhen(mixed $value, bool $strict = false): self
    {
        // todo - how to interact with values passed to retryWhenResult()?

        return $this;
    }

    /**
     * Specify a callback to call when the result is not successful.
     *
     * @return $this
     */
    public function callUponFailedResult(callable $callback): self
    {
        $this->failedResultCallbacks[] = $callback;

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
        // $this->strategy->reset()
        $this->reset()->runsBeforeFirstAttempt();

        // $this->strategy->step()
        while ($this->step()) {
            try {

                return $callback();

            } catch (Throwable $e) {

                $this->callExceptionCallbacks($e);

                // $this->strategy->isLastAttempt()
                $stop = ($this->isLastAttempt()) || (!$this->exceptionIsRetryable($e));
                if ($stop) {
                    if ($this->rethrowLastException) {
                        throw $e;
                    }
                    break;
                }
            }
        }

        return $default;
    }

    /**
     * Call the callbacks the caller specified that should be called when an exception occurs.
     *
     * @param Throwable $e The exception that occurred.
     * @return void
     */
    private function callExceptionCallbacks(Throwable $e): void
    {
        foreach ($this->failedExceptionCallbacks as $callback) {
            $this->callExceptionCallback($callback, $e);
        }
    }

    /**
     * Check if a retry is allowed based on the type of exception.
     *
     * @param Throwable $e The exception to check.
     * @return boolean
     */
    private function exceptionIsRetryable(Throwable $e): bool
    {
        // the user has specified to not catch exceptions
        if ($this->retryExceptions === false) {
            return false;
        }

        // the user has specified to catch all exceptions
        if ($this->retryExceptions === []) {
            return true;
        }

        // the user specified particular exceptions to catch, or callback/s to check with
        foreach ($this->retryExceptions as $retry) {

            if (is_string($retry)) {
                if ($e instanceof $retry) {
                    return true;
                }
            } elseif (is_callable($retry)) {
                if ($this->callExceptionCallback($retry, $e)) {
                    return true;
                }
            }
        }

        // no exceptions matched
        return false;
    }



    /**
     * Call a callback provided it accepts a Throwable of the same type as the exception passed.
     *
     * @param callable  $callable  The callback to call.
     * @param Throwable $exception The exception to pass to the callback.
     * @return mixed
     * @throws BackoffRuntimeException When the callback's expects more than 1 parameter.
     * @throws BackoffRuntimeException When the callback's parameter can't be resolved.
     */
    public function callExceptionCallback(callable $callable, Throwable $exception): mixed
    {
        // make sure the callable accepts 0 or 1 parameter
        $parameters = $this->generateCallbackParameters($callable, $exception);
        if (count($parameters) > 1) {
            throw BackoffRuntimeException::exceptionCallbackAcceptsMoreThan1Param(array_keys($parameters));
        }

        // make sure the exception was matched to a parameter before calling it, otherwise, skip it
        if (in_array(null, $parameters, true)) {
            return null;
        }

        return $callable(...$parameters);
    }

    /**
     * Generate the parameters to pass to a callback.
     *
     * @param callable  $callable  The callback that will be called.
     * @param Throwable $exception The exception that needs to be passed to the callback.
     * @return mixed[]
     * @throws BackoffRuntimeException When the callback's parameters can't be resolved.
     */
    public function generateCallbackParameters(callable $callable, Throwable $exception): array
    {
        // determine if the callable is a function or a method
        if (is_array($callable)) {
            // callable is an array, could be a static or instance method
            [$objectOrClass, $method] = $callable;
            $reflection = new ReflectionMethod($objectOrClass, $method);
        } elseif ($callable instanceof Closure || is_string($callable)) {
            // callable is a Closure or a function name
            $reflection = new ReflectionFunction($callable);
        } else {
            // callable is an invokable object
            $reflection = new ReflectionMethod($callable, '__invoke');
        }

        $return = [];
        foreach ($reflection->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $return[$parameterName] = $this->checkParameter($parameter, $exception);
        }

        return $return;
    }

    /**
     * Check a parameter to see if it matches the exception.
     *
     * @param ReflectionParameter $parameter The parameter to check.
     * @param Throwable           $exception The exception to check against.
     * @return Throwable|null
     * @throws BackoffRuntimeException When the parameter cannot be used.
     */
    private function checkParameter(ReflectionParameter $parameter, Throwable $exception): ?Throwable
    {
        $parameterName = $parameter->getName();
        $parameterType = $parameter->getType();

        // a parameter might have one type,
        // or several in the case of union and intersection types,
        // (or none when not specified)
        // collect them all into an array so they can all be checked (only one needs to match)
        $types = [];
        $typeHintJoinType = null;
        if ($parameterType instanceof ReflectionNamedType) {
            $types[] = $parameterType;
        } elseif ($parameterType instanceof ReflectionUnionType) {
            $typeHintJoinType = '|';
            $types = $parameterType->getTypes();
        } elseif ($parameterType instanceof ReflectionIntersectionType) {
            $typeHintJoinType = '&';
            $types = $parameterType->getTypes();
        } else {
            throw BackoffRuntimeException::invalidExceptionCallbackParameter($parameterName);
        }

        $foundExceptionType = false;
        $typeHintParts = [];
        /** @var ReflectionNamedType $type */
        foreach ($types as $type) {

            $typeHintParts[] = $type->getName();

            // ignore built-in types - int, float, etc
            if ($type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();
            if (!$this->paramIsAnExceptionOfSomeSort($className)) {
                continue;
            }
            $foundExceptionType = true;

            if ($this->isA($className, $exception)) {
                return $exception;
            }
        }

        $typeHint = $typeHintJoinType
            ? implode($typeHintJoinType, $typeHintParts)
            : $typeHintParts[0] ?? null;

        return $foundExceptionType
            ? null
            : throw BackoffRuntimeException::invalidExceptionCallbackParameter($parameterName, $typeHint);
    }

    /**
     * Check if a class is a Throwable or a subclass of Throwable.
     *
     * @param string $class The class to check.
     * @return boolean
     */
    private function paramIsAnExceptionOfSomeSort(string $class): bool
    {
        return (($class === 'Throwable') || (is_subclass_of($class, 'Throwable')));
    }

    /**
     * Check if a class is a type of exception.
     *
     * @param string    $class     The class to check.
     * @param Throwable $exception The exception to check against.
     * @return boolean
     */
    private function isA(string $class, Throwable $exception): bool
    {
        if ($class == get_class($exception)) {
            return true;
        }

        if (is_subclass_of($exception, $class)) {
            return true;
        }

        return false;
    }
}
