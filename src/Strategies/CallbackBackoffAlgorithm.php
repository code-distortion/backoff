<?php

namespace CodeDistortion\Backoff\Strategies;

use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;
use CodeDistortion\Backoff\Support\BackoffAlgorithmInterface;

/**
 * A class that provides a custom (callback-based) backoff algorithm.
 */
class CallbackBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    public bool $jitterMayBeApplied = true;

    /** @var callable The callback that will determine the delays to use. */
    private $callback;



    /**
     * Constructor
     *
     * @param callable $callback The callback that determines the delays to use:
     *                           function(int $retryNumber): int|float|false.
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
     * Note: This is intended to run in a stateless way, only using $retryNumber
     * and possibly $prevDelay to work out the next delay.
     *
     * @param integer            $retryNumber The retry being attempted.
     * @param integer|float|null $prevDelay   The previous delay used (if any).
     * @return integer|float|null
     * @throws BackoffRuntimeException Thrown when the callback returns an invalid value.
     */
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevDelay): int|float|null
    {
        $callback = $this->callback;
        $delay = $callback($retryNumber);

        if (!is_int($delay) && !is_float($delay) && (!is_null($delay))) {
            throw BackoffRuntimeException::customBackoffCallbackGaveInvalidReturnValue();
        }

        return $delay;
    }
}
