<?php

namespace CodeDistortion\Backoff\Tests\Unit\Support;

use Throwable;

/**
 * An invokable class that can be used as a callback for the Backoff class.
 */
class InvokableExceptionCallback
{
    /**
     * Allow this class to be called as a function by the Backoff class.
     *
     * @param Throwable $e The exception that was thrown.
     * @return void
     */
    public function __invoke(Throwable $e): void
    {
    }
}
