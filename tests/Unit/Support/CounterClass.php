<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit\Support;

/**
 * A class that counts.
 */
class CounterClass
{
    /** @var integer The counter. */
    private int $count = 0;



    /**
     * Increment the counter.
     *
     * @return void
     */
    public function increment(): void
    {
        $this->count++;
    }

    /**
     * Get the count value.
     *
     * @return integer
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
