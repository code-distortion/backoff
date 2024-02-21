<?php

namespace CodeDistortion\Backoff\Exceptions;

/**
 * The base Backoff exception class.
 */
class BackoffRuntimeException extends BackoffException
{
    /**
     * The callback used in a CallbackBackoffAlgorithm gave an invalid return value.
     *
     * @return self
     */
    public static function customBackoffCallbackGaveInvalidReturnValue(): self
    {
        return new self('The CallbackBackoffAlgorithm callback gave an invalid return value');
    }

    /**
     * Backoff strategies cannot be reconfigured after they've started.
     *
     * @param string $method The method that was called.
     * @return self
     */
    public static function attemptToChangeAfterStart(string $method): self
    {
        return new self(
            "Backoff strategies cannot be reconfigured after starting - attempted to call \"$method\""
        );
    }

    /**
     * A callback could not be called because it does not accept 0 or 1 parameters.
     *
     * @param string[] $parameters The parameters that the callback accepts.
     * @return self
     */
    public static function exceptionCallbackAcceptsMoreThan1Param(array $parameters): self
    {
        $count = count($parameters);
        $parameters = implode(', ', $parameters);
        return new self("Exception callback must accept 0 or 1 parameters, but it accepts $count ($parameters)");
    }

    /**
     * A callback could not be called because it contains a parameter that is not a Throwable.
     *
     * @param string      $parameter The parameter that is not a Throwable.
     * @param string|null $type      The parameter's type.
     * @return self
     */
    public static function invalidExceptionCallbackParameter(string $parameter, ?string $type = null): self
    {
        return $type
            ? new self("Callback parameter \"$type \$$parameter\" is not expecting a \Throwable")
            : new self("Callback parameter \"\$$parameter\" is not expecting a \Throwable");
    }
}
