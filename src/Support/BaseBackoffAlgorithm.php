<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Support;

use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;

/**
 * Abstract class for classes that provide a backoff algorithm.
 */
abstract class BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    protected bool $jitterMayBeApplied = true;



    /**
     * Run through the sequence and report the generated delays as an array.
     *
     * @internal - For package testing purposes.
     *
     * @param integer $maxSteps The maximum number of steps to run through.
     * @return array<integer|float|null>
     */
    public function generateTestSequence(int $maxSteps): array
    {
        $delays = [];

        $prevDelay = null;
        // @infection-ignore-all $count++ -> $count-- (takes up too much memory)
        for ($count = 1; $count <= $maxSteps; $count++) {
            $delays[] = $prevDelay = $this->calculateBaseDelay($count, $prevDelay);
        }

        return $delays;
    }

    /**
     * Check if jitter may be applied to the delays produced by this algorithm.
     *
     * @return boolean
     */
    public function jitterMayBeApplied(): bool
    {
        return $this->jitterMayBeApplied;
    }
}
