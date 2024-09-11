<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Exceptions;

/**
 * The Backoff class for runtime exceptions.
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
     * When startOfAttempt() is called after the Backoff has stopped.
     *
     * @return self
     */
    public static function startOfAttemptNotAllowed(): self
    {
        return new self("Method ->startOfAttempt() cannot be called after the Backoff has stopped");
    }

    /**
     * When endOfAttempt() is called without startOfAttempt() being called first.
     *
     * @return self
     */
    public static function attemptLogHasNotStarted(): self
    {
        return new self("Method ->endOfAttempt() was called without ->startOfAttempt() being called first");
    }
}
