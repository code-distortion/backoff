<?php

namespace CodeDistortion\Backoff\Tests\Unit\Support;

use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * A class implementing only the BackoffStrategyTrait (not BackoffDecoratorTrait or BackoffRunnerTrait).
 */
class BackoffStrategy
{
    use BackoffStrategyTrait;
}
