<?php

namespace CodeDistortion\Backoff\Jitter;

use CodeDistortion\Backoff\Support\BaseJitter;
use CodeDistortion\Backoff\Support\JitterInterface;

/**
 * A class that lets a callback apply jitter to delays.
 */
class CallbackJitter extends BaseJitter implements JitterInterface
{
    /** @var callable The callback that will apply the jitter. */
    private $callback;



    /**
     * Constructor
     *
     * @param callable $callback The callback that will apply the jitter: function(int|float $delay): int|float.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Apply jitter to a delay.
     *
     * Note: This is intended to run in a stateless way, only using $delay and its settings to work out the delay.
     *
     * @param integer|float $delay The delay to apply jitter to.
     * @return integer|float
     */
    public function apply(int|float $delay): int|float
    {
        $callback = $this->callback;
        return $callback($delay);
    }
}
