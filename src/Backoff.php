<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff;

use CodeDistortion\Backoff\Traits\BackoffStrategyDecoratorTrait;
use CodeDistortion\Backoff\Traits\BackoffRunnerTrait;
use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * Represents a backoff strategy instance, implementing calculations for delays between retries and performs the sleeps.
 *
 * Also handles the retry loop, retrying when exceptions occur and when "failed" values are returned.
 */
final class Backoff
{
    use BackoffStrategyDecoratorTrait;
    use BackoffRunnerTrait;
    use BackoffStrategyTrait;
}
