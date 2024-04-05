<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit\Support;

/**
 * An invokable class that can records whether it's been invoked or not.
 */
class InvokableClass
{
    /** @var integer A count of how many times __invoke() is called. */
    private int $count = 0;



    /**
     * Allow this class to be called as a function by the Backoff class.
     *
     * @param mixed $e The exception that was thrown, or the invalid result that was returned.
     * @return void
     */
    public function __invoke(mixed $e): void
    {
        $this->count++;
    }

    /**
     * Get the count of how many times __invoke() has been called.
     *
     * @return integer
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
