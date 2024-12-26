<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Support\PossibleMatch;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test the PossibleMatch class.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class PossibleMatchUnitTest extends PHPUnitTestCase
{
    /**
     * Test the constructor.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_possible_match_constructor(): void
    {
        $possibleMatch = new PossibleMatch('value', true, 'default', true);
        self::assertSame('value', $possibleMatch->value);
        self::assertSame(true, $possibleMatch->hasDefault);
        self::assertSame('default', $possibleMatch->default);
        self::assertSame(true, $possibleMatch->strict);

        $possibleMatch = new PossibleMatch();
        self::assertSame(null, $possibleMatch->value);
        self::assertSame(false, $possibleMatch->hasDefault);
        self::assertSame(null, $possibleMatch->default);
        self::assertSame(false, $possibleMatch->strict);
    }
}
