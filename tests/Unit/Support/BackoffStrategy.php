<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit\Support;

use CodeDistortion\Backoff\Traits\BackoffStrategyTrait;

/**
 * A class implementing only the BackoffStrategyTrait (not BackoffStrategyDecoratorTrait or BackoffRunnerTrait).
 *
 * This is done here to test the BackoffStrategyTrait in isolation.
 */
class BackoffStrategy
{
    use BackoffStrategyTrait;
}
