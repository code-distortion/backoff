<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit;

use CodeDistortion\Backoff\Algorithms\FixedBackoffAlgorithm;
use CodeDistortion\Backoff\Algorithms\LinearBackoffAlgorithm;
use CodeDistortion\Backoff\Backoff;
use CodeDistortion\Backoff\Exceptions\BackoffRuntimeException;
use CodeDistortion\Backoff\Jitter\FullJitter;
use CodeDistortion\Backoff\Jitter\RangeJitter;
use CodeDistortion\Backoff\Settings;
use CodeDistortion\Backoff\Tests\PHPUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

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
    #[Test]
    public static function test_the_alternative_constructors(): void
    {
        // fixed
        $delays = Backoff::fixed(5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixed(5)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 5, 5, 5, 5, 5, 5, 5, 5, 5], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // fixed - in milliseconds
        $delays = Backoff::fixedMs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixedMs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame([5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // fixed - in microseconds
        $delays = Backoff::fixedUs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fixedUs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame([5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000, 5000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // linear
        $delays = Backoff::linear(5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linear(5)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 10, 15, 20, 25, 30, 35, 40, 45, 50], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::linear(5, 10)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linear(5, 10)->noJitter()->generateTestSequence(10);
        self::assertSame([5, 15, 25, 35, 45, 55, 65, 75, 85, 95], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // linear - in milliseconds
        $delays = Backoff::linearMs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearMs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 10_000, 15_000, 20_000, 25_000, 30_000, 35_000, 40_000, 45_000, 50_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::linearMs(5000, 10_000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearMs(5000, 10_000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // linear - in microseconds
        $delays = Backoff::linearUs(5000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearUs(5000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 10_000, 15_000, 20_000, 25_000, 30_000, 35_000, 40_000, 45_000, 50_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::linearUs(5000, 10_000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::linearUs(5000, 10_000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // exponential
        $delays = Backoff::exponential(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponential(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 4, 8, 16, 32, 64, 128, 256, 512], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::exponential(1, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponential(1, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1.0, 1.5, 2.25, 3.375, 5.0625, 7.59375, 11.390625, 17.0859375, 25.62890625, 38.443359375],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // exponential - in milliseconds
        $delays = Backoff::exponentialMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 4000, 8000, 16_000, 32_000, 64_000, 128_000, 256_000, 512_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::exponentialMs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialMs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000.0, 1500.0, 2250.0, 3375.0, 5062.5, 7593.75, 11_390.625, 17_085.9375, 25_628.90625, 38_443.359375],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // exponential - in microseconds
        $delays = Backoff::exponentialUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 4000, 8000, 16_000, 32_000, 64_000, 128_000, 256_000, 512_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::exponentialUs(1000, 1.5)->generateTestSequence(10);
        $delaysNoJitter = Backoff::exponentialUs(1000, 1.5)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000.0, 1500.0, 2250.0, 3375.0, 5062.5, 7593.75, 11_390.625, 17_085.9375, 25_628.90625, 38_443.359375],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // polynomial
        $delays = Backoff::polynomial(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomial(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 4, 9, 16, 25, 36, 49, 64, 81, 100], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::polynomial(1, 1.4)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomial(1, 1.4)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1.0,
                2.6390158215457884,
                4.655536721746079,
                6.964404506368992,
                9.518269693579391,
                12.286035066475314,
                15.245344971379456,
                18.379173679952558,
                21.674022167526225,
                25.118864315095795,
            ],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // polynomial - in milliseconds
        $delays = Backoff::polynomialMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 4000, 9000, 16000, 25000, 36000, 49000, 64000, 81000, 100000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::polynomialMs(1000, 1.4)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialMs(1000, 1.4)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1000.0,
                2639.015821545788,
                4655.536721746079,
                6964.404506368992,
                9518.269693579392,
                12_286.035066475315,
                15_245.344971379456,
                18_379.173679952557,
                21_674.022167526226,
                25_118.864315095794,
            ],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // polynomial - in microseconds
        $delays = Backoff::polynomialUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 4000, 9000, 16000, 25000, 36000, 49000, 64000, 81000, 100000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::polynomialUs(1000, 1.4)->generateTestSequence(10);
        $delaysNoJitter = Backoff::polynomialUs(1000, 1.4)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [
                1000.0,
                2639.015821545788,
                4655.536721746079,
                6964.404506368992,
                9518.269693579392,
                12_286.035066475315,
                15_245.344971379456,
                18_379.173679952557,
                21_674.022167526226,
                25_118.864315095794,
            ],
            $delaysNoJitter->getDelays()
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // fibonacci
        $delays = Backoff::fibonacci(1)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5, 8, 13, 21, 34, 55, 89], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacci(1, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1, false)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 2, 3, 5, 8, 13, 21, 34, 55, 89], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacci(1, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacci(1, true)->noJitter()->generateTestSequence(10);
        self::assertSame([1, 1, 2, 3, 5, 8, 13, 21, 34, 55], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // fibonacci - in milliseconds
        $delays = Backoff::fibonacciMs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter->getDelays()
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacciMs(1000, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000, false)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacciMs(1000, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciMs(1000, true)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // fibonacci - in microseconds
        $delays = Backoff::fibonacciUs(1000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacciUs(1000, false)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000, false)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000, 89_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        $delays = Backoff::fibonacciUs(1000, true)->generateTestSequence(10);
        $delaysNoJitter = Backoff::fibonacciUs(1000, true)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [1000, 1000, 2000, 3000, 5000, 8000, 13_000, 21_000, 34_000, 55_000],
            $delaysNoJitter->getDelays()
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // decorrelated (no jitter is applied to this algorithm)
        $initialDelay = 1;
        $multiplier = 3;
        $backoff = Backoff::decorrelated($initialDelay, $multiplier);

        // check max-attempts first
        self::assertCount(10, $backoff->generateTestSequence(10)->getDelays());

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            // just test that it generates numbers, the BackoffAlgorithmUnitTest tests the actual values
            self::assertGreaterThanOrEqual($initialDelay, $delay);
        }

        // decorrelated - in milliseconds
//        $backoff = Backoff::decorrelatedMs(1);
//        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays->getDelaysInMs());

        // decorrelated - in microseconds
//        $backoff = Backoff::decorrelatedUs(1);
//        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delay->getDelaysInUs());



        // random (no jitter is applied to this algorithm)
        $min = mt_rand(0, 100000);
        $max = mt_rand($min, $min + 10);
        $backoff = Backoff::random($min, $max);

        // check max-attempts first
        self::assertCount(10, $backoff->generateTestSequence(10)->getDelays());

        $backoff->reset()->maxAttempts(null);
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual($min, $delay);
            self::assertLessThanOrEqual($max, $delay);
        }

        // random - in milliseconds
//        $backoff = Backoff::randomMs($min, $max);
//        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delays->getDelaysInMs());

        // random - in microseconds
//        $backoff = Backoff::randomUs($min, $max);
//        $delays = $backoff->generateTestSequence(10);
//        self::assertSame([ ??? ], $delay->getDelaysInUs());



        // sequence
        $delays = Backoff::sequence([9, 8, 7, 6, 5])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequence([9, 8, 7, 6, 5])->noJitter()->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default

        $delays = Backoff::sequence([9, 8, 7, 6, 5], 4)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequence([9, 8, 7, 6, 5], 4)->noJitter()->generateTestSequence(10);
        self::assertSame([9, 8, 7, 6, 5, 4, 4, 4, 4, 4], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default

        // sequence - in milliseconds
        $delays = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000])
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default

        $delays = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000], 4000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceMs([9000, 8000, 7000, 6000, 5000], 4000)
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000, 4000, 4000, 4000, 4000, 4000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default

        // sequence - in microseconds
        $delays = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000])->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000])
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default

        $delays = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000], 4000)->generateTestSequence(10);
        $delaysNoJitter = Backoff::sequenceUs([9000, 8000, 7000, 6000, 5000], 4000)
            ->noJitter()
            ->generateTestSequence(10);
        self::assertSame([9000, 8000, 7000, 6000, 5000, 4000, 4000, 4000, 4000, 4000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertSame($delays->getDelays(), $delaysNoJitter->getDelays()); // no jitter is applied by default



        // callback
        $callback = fn($retryNumber) => $retryNumber < 4
            ? $retryNumber * 1000
            : null;
        $delays = Backoff::callback($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callback($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // callback - in milliseconds
        $delays = Backoff::callbackMs($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callbackMs($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // callback - in microseconds
        $delays = Backoff::callbackUs($callback)->generateTestSequence(10);
        $delaysNoJitter = Backoff::callbackUs($callback)->noJitter()->generateTestSequence(10);
        self::assertSame([1000, 2000, 3000], $delaysNoJitter->getDelays());
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // custom
        $algorithm = new LinearBackoffAlgorithm(5000, 10_000);

        $delays = Backoff::custom($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::custom($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // custom - in milliseconds
        $delays = Backoff::customMs($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::customMs($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());

        // custom - in microseconds
        $delays = Backoff::customUs($algorithm)->generateTestSequence(10);
        $delaysNoJitter = Backoff::customUs($algorithm)->noJitter()->generateTestSequence(10);
        self::assertSame(
            [5000, 15_000, 25_000, 35_000, 45_000, 55_000, 65_000, 75_000, 85_000, 95_000],
            $delaysNoJitter->getDelays(),
        );
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
        self::assertNotSame($delays->getDelays(), $delaysNoJitter->getDelays());



        // noop
        $delays = Backoff::noop()->generateTestSequence(10);
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $delays->getDelays());



        // none
        $delays = Backoff::none()->generateTestSequence(10);
        self::assertSame([], $delays->getDelays());
    }



    /**
     * Test the jitter setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_jitter_setters(): void
    {
        // custom jitter
        $jitter = new RangeJitter(0.75, 1.25);
        $backoff = Backoff::fixed(1)->noAttemptLimit()->customJitter($jitter);
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual(0.75, $delay);
            self::assertLessThanOrEqual(1.25, $delay);
        }

        // full jitter
        $backoff = Backoff::fixed(1)->noAttemptLimit()->fullJitter();
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(1, $delay);
        }

        // equal jitter
        $backoff = Backoff::fixed(1)->noAttemptLimit()->equalJitter();
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual(0.5, $delay);
            self::assertLessThanOrEqual(1, $delay);
        }

        // jitter range
        $backoff = Backoff::fixed(1)->noAttemptLimit()->jitterRange(0.75, 1.25);
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertGreaterThanOrEqual(0.75, $delay);
            self::assertLessThanOrEqual(1.25, $delay);
        }

        // callback jitter
        $callback = fn(int $delay) => mt_rand($delay, $delay + 3);
        $backoff = Backoff::fixed(1)->noAttemptLimit()->jitterCallback($callback);
        foreach ($backoff->generateTestSequence(100)->getDelays() as $delay) {
            self::assertTrue(in_array($delay, [1, 2, 3, 4]));
        }

        // no jitter
        $backoff = Backoff::fixed(4)->noAttemptLimit()->fullJitter()->noJitter();
        self::assertSame([4, 4, 4, 4, 4], $backoff->generateTestSequence(5)->getDelays());
    }



    /**
     * Test the max-attempt setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_max_attempt_setters(): void
    {
        // max attempts = 0
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(0);
        self::assertSame([], $backoff->generateTestSequence(10)->getDelays());

        // max attempts
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(8);
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $backoff->generateTestSequence(10)->getDelays());

        // no max attempts
        $backoff = Backoff::linear(1)->noJitter()->maxAttempts(8)->noAttemptLimit();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());



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
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());
    }



    /**
     * Test the max-delay setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_max_delay_setters(): void
    {
        // max attempts
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5);
        self::assertSame([1, 2, 3, 4, 5, 5, 5, 5, 5, 5], $backoff->generateTestSequence(10)->getDelays());

        // no max attempts
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5)->noDelayLimit();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());

        // check the noMaxDelay() alias for noDelayLimit()
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->maxDelay(5)->noMaxDelay();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());
    }



    /**
     * Test the unit-of-measure setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_unit_of_measure_setters(): void
    {
        // unit - Settings::UNIT_SECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_SECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());

        // unit - Settings::UNIT_MILLISECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MILLISECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());

        // unit - Settings::UNIT_MICROSECONDS
        $backoff = Backoff::linear(1)->unit(Settings::UNIT_MICROSECONDS);
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());

        // seconds
        $backoff = Backoff::linear(1)->unitSeconds();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInSeconds());

        // milliseconds
        $backoff = Backoff::linear(1)->unitMs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInMs());

        // microseconds
        $backoff = Backoff::linear(1)->unitUs();
        $delays = $backoff->generateTestSequence(5);
        self::assertSame($delays->getDelays(), $delays->getDelaysInUs());
    }



    /**
     * Test the runs-before-first-attempt setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_runs_before_first_attempt_setters(): void
    {
        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop();
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)->getDelays());

        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop(true);
        self::assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)->getDelays());

        // include the initial attempt
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->runsAtStartOfLoop(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());

        // don't include the initial attempt
        $backoff = Backoff::linear(1)
            ->noJitter()
            ->noAttemptLimit()
            ->runsAtStartOfLoop()
            ->runsAtEndOfLoop();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());
    }



    /**
     * Test the immediate-first-retry setters.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_immediate_first_retry_setters(): void
    {
        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry();
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)->getDelays());

        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry(true);
        self::assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $backoff->generateTestSequence(10)->getDelays());

        // insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry(false);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());

        // don't insert a 0 delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->immediateFirstRetry()->noImmediateFirstRetry();
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $backoff->generateTestSequence(10)->getDelays());
    }



    /**
     * Test the only-delay-when setter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_only_delay_when_setter(): void
    {
        // enable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)->getDelays());

        // disable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(false);
        self::assertSame([0, 0, 0, 0, 0], $backoff->generateTestSequence(5)->getDelays());

        // disable then re-enable the delay
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyDelayWhen(false)->onlyDelayWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)->getDelays());
    }



    /**
     * Test the only-retry-when setter.
     *
     * @test
     *
     * @return void
     */
    #[Test]
    public static function test_the_only_retry_when_setter(): void
    {
        // enable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)->getDelays());

        // disable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(false);
        self::assertSame([], $backoff->generateTestSequence(5)->getDelays());

        // disable then re-enable retries
        $backoff = Backoff::linear(1)->noJitter()->noAttemptLimit()->onlyRetryWhen(false)->onlyRetryWhen(true);
        self::assertSame([1, 2, 3, 4, 5], $backoff->generateTestSequence(5)->getDelays());
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
    #[Test]
    #[DataProvider('updateBackoffCallbackProvider')]
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
     * @return array<array<callable>>
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
