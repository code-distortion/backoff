<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Algorithms;

use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;

/**
 * A class that provides a custom (callback-based) backoff algorithm.
 */
class CallbackBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    protected bool $jitterMayBeApplied = true;

    /** @var callable The callback that will determine the delays to use. */
    private $callback;



    /**
     * Constructor
     *
     * @param callable $callback The callback that determines the delays to use:
     *                           function(int $retryNumber, int|float|null $prevBaseDelay): int|float|false.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Calculate the delay needed before retrying an action.
     *
     * $retryNumber starts at 1 and increases for each subsequent retry.
     *
     * Note: This is intended to run in a stateless way, using only $retryNumber
     * and possibly $prevBaseDelay to work out the next delay.
     *
     * @param integer            $retryNumber   The retry being attempted.
     * @param integer|float|null $prevBaseDelay The previous delay used (if any).
     * @return integer|float|null
     * @throws BackoffRuntimeException Thrown when the callback returns an invalid result.
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null
    {
        $callback = $this->callback;
        $delay = $callback($retryNumber, $prevBaseDelay);

        if (!is_int($delay) && !is_float($delay) && (!is_null($delay))) {
            throw BackoffRuntimeException::customBackoffCallbackGaveInvalidReturnValue();
        }

        return $delay;
    }
}
