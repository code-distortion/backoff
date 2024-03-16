<?php

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;

/**
 * Test the BackoffDecoratorTrait.
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class BackoffDecoratorTraitUnitTest extends PHPUnitTestCase
{
    /**
     * Test Backoff's alternative constructors.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_alternative_constructors(): void
    {
        // fixed
        $delaysDefault = Backoff::fixed(5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixed(5)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 5, 5, 5, 5, 5, 5, 5, 5, 5], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // fixed - in milliseconds
        $delaysDefault = Backoff::fixedMs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixedMs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame([5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // fixed - in microseconds
        $delaysDefault = Backoff::fixedUs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixedUs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame([5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // linear
        $delaysDefault = Backoff::linear(5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linear(5)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 10, 15, 20, 25, 30, 35, 40, 45, 50], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::linear(5, 10)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linear(5, 10)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 15, 25, 35, 45, 55, 65, 75, 85, 95], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // linear - in milliseconds
        $delaysDefault = Backoff::linearMs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearMs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 10_000, 15_000, 20_000, 25_000, 30_000, 35_000, 40_000, 45_000, 50_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::linearMs(5000, 10_000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearMs(5000, 10_000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // linear - in microseconds
        $delaysDefault = Backoff::linearUs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearUs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 10_000, 15_000, 20_000, 25_000, 30_000, 35_000, 40_000, 45_000, 50_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::linearUs(5000, 10_000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearUs(5000, 10_000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // exponential
        $delaysDefault = Backoff::exponential(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponential(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 4, 8, 16, 32, 64, 128, 256, 512], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::exponential(1, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponential(1, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1.0, 1.5, 2.25, 3.375, 5.0625, 7.59375, 11.390625, 17.0859375, 25.62890625, 38.443359375],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // exponential - in milliseconds
        $delaysDefault = Backoff::exponentialMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 4000, 8000, 16_000, 32_000, 64_000, 128_000, 256_000, 512_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::exponentialMs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialMs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000.0, 1500.0, 2250.0, 3375.0, 5062.5, 7593.75, 11_390.625, 17_085.9375, 25_628.90625, 38_443.359375],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // exponential - in microseconds
        $delaysDefault = Backoff::exponentialUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 4000, 8000, 16_000, 32_000, 64_000, 128_000, 256_000, 512_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::exponentialUs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialUs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000.0, 1500.0, 2250.0, 3375.0, 5062.5, 7593.75, 11_390.625, 17_085.9375, 25_628.90625, 38_443.359375],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // polynomial
        $delaysDefault = Backoff::polynomial(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomial(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 4, 9, 16, 25, 36, 49, 64, 81, 100], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::polynomial(1, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomial(1, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1.0,
                2.8284271247461903,
                5.196152422706632,
                8.0,
                11.180339887498949,
                14.696938456699069,
                18.520259177452136,
                22.627416997969522,
                27.0,
                31.622776601683793,
            ],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // polynomial - in milliseconds
        $delaysDefault = Backoff::polynomialMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 4000, 9000, 16000, 25000, 36000, 49000, 64000, 81000, 100000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::polynomialMs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialMs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1000.0,
                2828.42712474619,
                5196.152422706632,
                8000.0,
                11_180.339887498949,
                14_696.938456699069,
                18_520.259177452136,
                22_627.416997969522,
                27_000.0,
                31_622.776601683793,
            ],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // polynomial - in microseconds
        $delaysDefault = Backoff::polynomialUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 4000, 9000, 16000, 25000, 36000, 49000, 64000, 81000, 100000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::polynomialUs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialUs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1000.0,
                2828.42712474619,
                5196.152422706632,
                8000.0,
                11_180.339887498949,
                14_696.938456699069,
                18_520.259177452136,
                22_627.416997969522,
                27_000.0,
                31_622.776601683793,
            ],
            $delaysNoJitter['delay']
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // fibonacci
        $delaysDefault = Backoff::fibonacci(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5, 8, 13, 21, 34, 55, 89], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacci(1, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1, false)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5, 8, 13, 21, 34, 55, 89], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacci(1, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1, true)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 1, 2, 3, 5, 8, 13, 21, 34, 55], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // fibonacci - in milliseconds
        $delaysDefault = Backoff::fibonacciMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter['delay']
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacciMs(1000, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000, false)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacciMs(1000, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000, true)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // fibonacci - in microseconds
        $delaysDefault = Backoff::fibonacciUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacciUs(1000, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000, false)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        $delaysDefault = Backoff::fibonacciUs(1000, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000, true)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000],
            $delaysNoJitter['delay']
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // decorrelated (no jitter is applied to this algorithm)
        $initialDelay = 1;
        $multiplier = 3;
        $backoff = Backoff::decorrelated($initialDelay, $multiplier);

        // check max-attempts first
        self::assertCount(10, $backoff->generateTestSequence(10)['delay']);

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        // decorrelated - in milliseconds
        $backoff = Backoff::decorrelatedMs(1);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // decorrelated - in microseconds
        $backoff = Backoff::decorrelatedUs(1);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // random (no jitter is applied to this algorithm)
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $backoff = Backoff::random($min, $max);

        // check max-attempts first
        self::assertCount(10, $backoff->generateTestSequence(10)['delay']);

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        // random - in milliseconds
        $backoff = Backoff::randomMs($min, $max);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInMs']);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // random - in microseconds
        $backoff = Backoff::randomUs($min, $max);
        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays['delayInUs']);
        self::assertSame($delays['delay'], $delays['delayInUs']);



        // sequence
        $delaysDefault = Backoff::sequence([9, 8, 7, 6, 5])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequence([9, 8, 7, 6, 5])->noJitter()->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default

        $delaysDefault = Backoff::sequence([9, 8, 7, 6, 5], 4)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequence([9, 8, 7, 6, 5], 4)->noJitter()->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5, 4, 4, 4, 4, 4], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default

        // sequence - in milliseconds
        $delaysDefault = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000])
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default

        $delaysDefault = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000], 4000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000], 4000)
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000, 4000, 4000, 4000, 4000, 4000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default

        // sequence - in microseconds
        $delaysDefault = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000])
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default

        $delaysDefault = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000], 4000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000], 4000)
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000, 4000, 4000, 4000, 4000, 4000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertSame($delaysDefault['delay'], $delaysNoJitter['delay']); // no jitter is applied by default



        // callback
        $callback = fn($retryNumber) => $retryNumber < 4
            ? $retryNumber * 1000
            : null;
        $delaysDefault = Backoff::callback($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callback($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // callback - in milliseconds
        $delaysDefault = Backoff::callbackMs($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callbackMs($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // callback - in microseconds
        $delaysDefault = Backoff::callbackUs($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callbackUs($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter['delay']);
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // custom
        $algorithm = new LinearBackoffAlgorithm(5000, 10_000);

        $delaysDefault = Backoff::custom($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::custom($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInSeconds']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // custom - in milliseconds
        $delaysDefault = Backoff::customMs($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::customMs($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInMs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);

        // custom - in microseconds
        $delaysDefault = Backoff::customUs($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::customUs($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter['delay'],
        );
        self::assertSame($delaysDefault['delay'], $delaysDefault['delayInUs']);
        self::assertNotSame($delaysDefault['delay'], $delaysNoJitter['delay']);



        // noop
        $delaysDefault = Backoff::noop()->generateTestSequence(10);
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $delaysDefault['delay']);



        // none
        $delaysDefault = Backoff::none()->generateTestSequence(10);
        self::assertSame([], $delaysDefault['delay']);
    }



    /**
     * Test the jitter setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_jitter_setters(): void
    {
        // custom jitter
        $jitter = new RangeJitter(0.75, 1.25);
        $backoff = Backoff::fixed(1)->noAttemptLimit()->customJitter($jitter);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(0.75, $delay);
            self::assertLessThanOrEqual(1.25, $delay);
        }

        // full jitter
        $backoff = Backoff::fixed(1)->noAttemptLimit()->fullJitter();
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(1, $delay);
        }

        // equal jitter
        $backoff = Backoff::fixed(1)->noAttemptLimit()->equalJitter();
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(0.5, $delay);
            self::assertLessThanOrEqual(1, $delay);
        }

        // jitter range
        $backoff = Backoff::fixed(1)->noAttemptLimit()->jitterRange(0.75, 1.25);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertGreaterThanOrEqual(0.75, $delay);
            self::assertLessThanOrEqual(1.25, $delay);
        }

        // callback jitter
        $callback = fn(int $delay) => mt_rand($delay, $delay + 3);
        $backoff = Backoff::fixed(1)->noAttemptLimit()->jitterCallback($callback);
        foreach ($backoff->generateTestSequence(100)['delay'] as $delay) {
            self::assertTrue(in_array($delay, [1, 2, 3, 4]));
        }

        // no jitter
        $backoff = Backoff::fixed(4)->noAttemptLimit()->fullJitter()->noJitter();
        self::assertSame([4, 4, 4, 4, 4], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test the max-attempt setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_max_attempt_setters(): void
    {
        // max attempts = 0
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(0);
        self::assertSame([], $backoff->generateTestSequence(10)['delay']);

        // max attempts
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(8);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $backoff->generateTestSequence(10)['delay']);

        // no max attempts
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(8)->noAttemptLimit();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);



        // check that stopped is reassessed when maxAttempts() is called - from 0 to 0
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->maxAttempts(0) // <<<
            ->runsAtStartOfLoop();
        while ($backoff->step()) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(0, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 0 to 1
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->maxAttempts(1) // <<<
            ->runsAtStartOfLoop();
        while ($backoff->step()) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(1, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to 0
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->maxAttempts(0) // <<<
            ->runsAtStartOfLoop();
        while ($backoff->step()) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(0, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to 1
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->maxAttempts(1) // <<<
            ->runsAtStartOfLoop();
        while ($backoff->step()) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(1, $backoff->logs());



        // check that stopped is reassessed when noAttempts() is called - from 0 to null
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 0)
            ->unitUs()
            ->noAttemptLimit() // <<<
            ->runsAtStartOfLoop();
        $attempts = 5;
        while (($attempts-- > 0) && ($backoff->step())) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(5, $backoff->logs());

        // check that stopped is reassessed when maxAttempts() is called - from 1 to null
        $backoff = Backoff::new(new FixedBackoffAlgorithm(1), null, 1)
            ->unitUs()
            ->noAttemptLimit() // <<<
            ->runsAtStartOfLoop();
        $attempts = 5;
        while (($attempts-- > 0) && ($backoff->step())) {
            $backoff->startOfAttempt();
            $backoff->endOfAttempt();
        }
        self::assertCount(5, $backoff->logs());



        // check the noMaxAttempts() alias for noAttemptLimit()
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(8)->noMaxAttempts();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test the max-delay setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_max_delay_setters(): void
    {
        // max attempts
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5);
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $backoff->generateTestSequence(10)['delay']);

        // no max attempts
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5)->noDelayLimit();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // check the noMaxDelay() alias for noDelayLimit()
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5)->noMaxDelay();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test the unit-of-measure setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_unit_of_measure_setters(): void
    {
        // unit - Settings::UNIT_SECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_SECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInSeconds']);

        // unit - Settings::UNIT_MILLISECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MILLISECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // unit - Settings::UNIT_MICROSECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MICROSECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInUs']);

        // seconds
        $backoff = Backoff::linear(1)->unitSeconds();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInSeconds']);

        // milliseconds
        $backoff = Backoff::linear(1)->unitMs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInMs']);

        // microseconds
        $backoff = Backoff::linear(1)->unitUs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays['delay'], $delays['delayInUs']);
    }



    /**
     * Test the runs-before-first-attempt setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_runs_before_first_attempt_setters(): void
    {
        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop();
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop(true);
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't include the initial attempt
        $backoff = Backoff::linear(1)
            ->noJitter()
            ->noAttemptLimit()
            ->runsAtStartOfLoop()
            ->runsAtEndOfLoop();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test the immediate-first-retry setters.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_immediate_first_retry_setters(): void
    {
        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry();
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry(true);
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)['delay']);

        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);

        // don't insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry()->noImmediateFirstRetry();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)['delay']);
    }



    /**
     * Test the only-delay-when setter.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_only_delay_when_setter(): void
    {
        // enable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(false);
        self::assertSame([0, 0, 0, 0, 0], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(false)->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test the only-retry-when setter.
     *
     * @test
     *
     * @return void
     */
    public static function test_the_only_retry_when_setter(): void
    {
        // enable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);

        // disable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(false);
        self::assertSame([], $backoff->generateTestSequence(5)['delay']);

        // disable then re-enable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(false)->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)['delay']);
    }



    /**
     * Test that Backoff throws exceptions when settings change after starting.
     *
     * @test
     * @dataProvider updateBackoffCallbackProvider
     *
     * @param callable $updateBackoffCallback The callback to update the backoff.
     * @return void
     */
    public function test_that_exceptions_are_thrown_when_settings_change_after_starting(
        callable $updateBackoffCallback
    ): void {

        $this->expectException(BackoffRuntimeException::class);

        $backoff = Backoff::fixed(1)->unitUs();
        $backoff->step();
        $updateBackoffCallback($backoff);
    }

    /**
     * DataProvider for test_that_exceptions_are_thrown_after_starting.
     *
     * @return array
     */
    public static function updateBackoffCallbackProvider(): array
    {
        return [
            [fn(Backoff $backoff) => $backoff->fullJitter()],
            [fn(Backoff $backoff) => $backoff->equalJitter()],
            [fn(Backoff $backoff) => $backoff->jitterRange(0, 1)],
            [fn(Backoff $backoff) => $backoff->jitterCallback(fn(int $delay) => $delay)],
            [fn(Backoff $backoff) => $backoff->customJitter(new FullJitter())],
            [fn(Backoff $backoff) => $backoff->noJitter()],

            [fn(Backoff $backoff) => $backoff->maxAttempts(4)],
            [fn(Backoff $backoff) => $backoff->noAttemptLimit()],
            [fn(Backoff $backoff) => $backoff->noMaxAttempts()],

            [fn(Backoff $backoff) => $backoff->maxDelay(100)],
            [fn(Backoff $backoff) => $backoff->noMaxDelay()],

            [fn(Backoff $backoff) => $backoff->unit(Settings::UNIT_SECONDS)],
            [fn(Backoff $backoff) => $backoff->unitSeconds()],
            [fn(Backoff $backoff) => $backoff->unitMs()],
            [fn(Backoff $backoff) => $backoff->unitUs()],

            [fn(Backoff $backoff) => $backoff->runsAtStartOfLoop()],
            [fn(Backoff $backoff) => $backoff->runsAtEndOfLoop()],

            [fn(Backoff $backoff) => $backoff->immediateFirstRetry()],
            [fn(Backoff $backoff) => $backoff->noImmediateFirstRetry()],

            [fn(Backoff $backoff) => $backoff->onlyDelayWhen(true)],
            [fn(Backoff $backoff) => $backoff->onlyRetryWhen(true)],
        ];
    }
}
