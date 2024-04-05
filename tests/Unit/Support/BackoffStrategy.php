<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit\Support;

use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * A class implementing only the BackoffStrategyTrait (not BackoffDecoratorTrait or BackoffRunnerTrait).
 */
class BackoffStrategy
{
    use BackoffStrategyTrait;
}
