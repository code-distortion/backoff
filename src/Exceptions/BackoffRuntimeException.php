<?php

namespace CodeDistortion\Backoff\Exceptions;

/**
 * The base Backoff exception class.
 */
class BackoffRuntimeException extends BackoffException
{
    /**
     * The callback used in a CallbackBackoffStrategy gave an invalid return value.
     *
     * @return self
     */
    public static function customBackoffCallbackGaveInvalidReturnValue(): self
    {
        return new self('The CallbackBackoffStrategy callback gave an invalid return value');
    }

    /**
     * Backoff handlers cannot be reconfigured after they've started.
     *
     * @param string $method The method that was called.
     * @return self
     */
    public static function attemptToChangeAfterStart(string $method): self
    {
        return new self(
            "Backoff handlers cannot be reconfigured after starting - attempted to call \"$method\""
        );
    }
}
