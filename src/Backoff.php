<?php

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Traits\BackoffDecoratorTrait;
use CodeDistortion\Backoff\Traits\BackoffRunnerTrait;
use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * Represents a backoff strategy instance, implementing calculations for delays between retries and performs the sleeps.
 *
 * Also handles the retry loop, retrying when exceptions occur and when "failed" values are returned.
 *
 * todo - add interface/s to implement?
 */
final class Backoff
{
    use BackoffDecoratorTrait;
    use BackoffRunnerTrait;
    use BackoffStrategyTrait;
}
