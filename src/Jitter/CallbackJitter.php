<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Jitter;

use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Support\BaseJitter;

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
     * @param callable $callback The callback that will apply the jitter:
     *                           function(int|float $delay, int $retryNumber): int|float.
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
     * @param integer|float $delay       The delay to apply jitter to.
     * @param integer       $retryNumber The retry being attempted.
     * @return integer|float
     */
    public function apply(int|float $delay, int $retryNumber): int|float
    {
        $callback = $this->callback;
        $return = $callback($delay, $retryNumber);
        if (is_int($return) || is_float($return)) {
            return $return;
        }
        return 1;
    }
}
