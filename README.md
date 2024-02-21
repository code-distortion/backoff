# Would You Backoff
# Just Backoff a Little
# Just Backoff
# Just A Backoff Library

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/backoff.svg?style=flat-square)](https://packagist.org/packages/code-distortion/backoff)
XXXX ![PHP Version](https://img.shields.io/badge/PHP-8.0%20to%208.3-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/backoff/run-tests.yml?branch=master&style=flat-square)](https://github.com/code-distortion/backoff/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/backoff)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/backoff*** is a PHP Library that provides backoff when retrying actions that might fail.

A [very flexible set of options](#usage) are provided to control how long to wait between attempts, and how many attempts to make.

However most times, something similar to this is all that's needed:

``` php
$backoff = Backoff::exponential(10)
    ->unitMs()
    ->maxDelay(200)
    ->maxAttempts(10)
    ->fullJitter();

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```



## Table of Contents

- [Installation](#installation)
- [General Tips](#general-tips)
- [Usage](#usage)
  - [Max Attempts](#max-attempts)
  - [Unit-of-Measure](#unit-of-measure)
  - [Delay](#delay)
    - [Max-Delay](#max-delay)
    - [Immediate First Retry](#immediate-first-retry)
- [Backoff Strategies](#backoff-strategies)
  - [Fixed Backoff](#fixed-backoff)
  - [Linear Backoff](#linear-backoff)
  - [Exponential Backoff](#exponential-backoff)
  - [Polynomial Backoff](#polynomial-backoff)
  - [Fibonacci Backoff](#fibonacci-backoff)
  - [Decorrelated Backoff](#decorrelated-backoff)
  - [Random Backoff](#random-backoff)
  - [Sequence Backoff](#sequence-backoff)
  - [Callback Backoff](#callback-backoff)
  - [Custom Backoff Class](#custom-backoff-class)
  - [Noop Backoff](#noop-backoff)
  - [No Backoff](#no-backoff)
- [Jitter](#jitter)
  - [Full Jitter](#full-jitter)
  - [Equal Jitter](#equal-jitter)
  - [Custom Jitter Range](#custom-jitter-range)
  - [Jitter Callback](#jitter-callback)
  - [Custom Jitter Class](#custom-jitter-class)
- [Under-the-hood ???](#under-the-hood-???)
- [Logging](#logging)
- [Backoff When Running Tests](#backoff-when-running-tests)
  - [Disabling Backoff](#disabling-backoff)
  - [Disabling Retries](#disabling-retries)



## Installation

Install the package via composer:

``` bash
composer require code-distortion/backoff
```



## General Tips

- Backoff attempts are intended to be used when actions fail because of **transient** issues (such as temporary service outages). When **permanent** errors occur (such as a 404 HTTP response), retrying should stop as it won't help.
- Be careful when nesting backoff attempts. This can unexpectedly increase the number of attempts and time taken.
- Actions taken during backoff attempts should be idempotent. This means that if the same action is performed multiple times, the result should be the same as if it were only performed once.



## Usage

Start by choosing your [backoff strategy](#backoff-strategies) (e.g. exponential), configure it as needed, and then use it to control a loop.

`$backoff->step()` will wait the appropriate amount of time.

``` php
use CodeDistortion\Backoff\Backoff;

$backoff = Backoff::exponential(1);

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```



### Max Attempts

To stop the delays from increasing indefinitely, you can specify the maximum number of attempts that are allowed to be made.

`$backoff->step()` will return `false` when it's exhausted its attempts.

``` php
$backoff = Backoff::exponential(1)->maxAttempts(5); // <<<

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```

If you'd like to run your code *zero* or more times, you can place `$backoff->step()` at the *entrance* to your loop, having called `->runsBeforeFirstAttempt()` beforehand. This lets backoff know so it doesn't perform the delay and count the attempt the first time.

``` php
$maxAttempts = …; // possibly 0

$backoff = Backoff::exponential(1)
    ->maxAttempts($maxAttempts) // <<<
    ->runsBeforeFirstAttempt(); // <<<

$success = false;
while ((!$success) && ($backoff->step())) {
    $success = …; // do some work
};
```



### Unit-of-Measure

By default, Backoff uses *seconds* as its unit-of-measure.

You can specify the number of seconds as a fraction (e.g. *0.25*), and you can change the unit-of-measure to *milliseconds* or *microseconds* if you prefer.

> 1 second (s) = 1,000 milliseconds (ms) = 1,000,000 microseconds (µs)

``` php
$backoff = Backoff::exponential(1)
    ->unitSeconds() // seconds (s), or
    ->unitMs()      // milliseconds (ms), or
    ->unitUs();     // microseconds (µs)
```

As an alternative, you can call `->unit(…)` and pass in one of the `Settings::UNIT_XXX` constants:
``` php
use CodeDistortion\Backoff\Settings; 

$backoff = Backoff::exponential(1)
    ->unit(Settings::UNIT_SECONDS)
    ->unit(Settings::UNIT_MILLISECONDS)
    ->unit(Settings::UNIT_MICROSECONDS)
```

> ***Note:*** Microseconds are probably small enough that the numbers start to become inaccurate because of PHP overheads. For example, on my computer, running usleep(1) to sleep for 1 microsecond actually takes about 55 microseconds.



### Delay

#### Max-Delay

You can specify the maximum delay the backoff will wait for each time. This is useful for preventing the delays from becoming too long.

> ***Note:*** You'll need to make sure the max-delay you specify matches the [unit-of-measure](#unit-of-measure) being used.

``` php
$backoff = Backoff::exponential(10)
    ->unitMs()
    ->maxDelay(200); // <<<
```



#### Immediate First Retry

If you'd like your first retry to occur immediately, you can add an initial 0 delay by calling `->immediateFirstRetry()`. This will be inserted before the normal backoff delays start.

``` php
$backoff = Backoff::exponential(10)
    ->unitMs()
    ->fullJitter()
    ->maxDelay(200)
    ->maxAttempts(10);
    ->immediateFirstRetry(); // <<< 0, 10, 20, 40, 80…

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```

This won't affect the maximum attempt limit.



## Backoff Strategies

Backoff strategies are the algorithms used to determine how long to wait between attempts. They usually increase the delay between attempts.

Several backoff strategies have been included for your convenience. You can also [create your own]() [todo]

``` php
Backoff::fixed(2); // 2, 2, 2, 2, 2…
Backoff::linear(5); // 5, 10, 15, 20, 25…
Backoff::linear(5, 10); // 5, 15, 25, 35, 45…
Backoff::exponential(1); // 1, 2, 4, 8, 16…
Backoff::exponential(1, 1.5); // 1, 1.5, 2.25, 3.375, 5.0625…
Backoff::polynomial(1); // 1, 4, 9, 16, 25…
Backoff::polynomial(1, 1.5); //  1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…
Backoff::fibonacci(1); //  1, 1, 2, 3, 5…
Backoff::random(2, 5); // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137… 
Backoff::sequence([1, 1.25, 1.5, 2, 3]); // 1, 1.25, 1.5, 2, 3
Backoff::callback($callback); // $callback(1), $callback(2), $callback(3), $callback(4), $callback(5)… 
Backoff::noop(); // 0, 0, 0, 0, 0… 
```

### Fixed Backoff

The fixed backoff strategy will wait the same amount of time between attempts.

``` php
$backoff = Backoff::fixed(2); // 2, 2, 2, 2, 2…
```



### Linear Backoff

The linear backoff strategy increases the period it waits by a fixed amount each time.

The delay to increase by defaults to `$initialDelay` when not set.

`$delay = $initialDelay + (($retryNumber - 1) * $delayIncrease)`

``` php
// Backoff::linear($initalDelay, $delayIncrease = null);

$backoff = Backoff::linear(5);     // 5, 10, 15, 20, 25…
$backoff = Backoff::linear(5, 10); // 5, 15, 25, 35, 45…
```



### Exponential Backoff

The exponential backoff strategy increases the period it waits exponentially.

By default, it doubles the delay each time, but you can change the factor it multiplies by.

`$delay = $initialDelay * pow($factor, $retryNumber - 1)`

``` php
// Backoff::exponential($initalDelay, $factor = 2);

$backoff = Backoff::exponential(1);      // 1, 2, 4, 8, 16…
$backoff = Backoff::exponential(1, 1.5); // 1, 1.5, 2.25, 3.375, 5.0625…
```



### Polynomial Backoff

The polynomial backoff strategy increases the period it waits in a polynomial fashion.

By default, the power to which the retry number is raised is 2, but you can change this.

`$delay = $initialDelay * pow($retryNumber, $power)`

``` php
// Backoff::polynomial($initialDelay, $power = 2);

$backoff = Backoff::polynomial(1);      // 1, 4, 9, 16, 25…
$backoff = Backoff::polynomial(1, 1.5); // 1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…
```



### Fibonacci Backoff

The Fibonacci backoff strategy increases the period it waits by following a Fibonacci sequence. This is where each delay is the sum of the previous two delays.

`$delay = $previousDelay1 + $previousDelay2`

``` php
// Backoff::fibonacci($initialDelay, $includeFirst = false);

$backoff = Backoff::fibonacci(1); // 1, 2, 3, 5, 8, 13, 21, 34, 55, 89…
$backoff = Backoff::fibonacci(5); // 5, 10, 15, 25, 40, 65, 105, 170, 275, 445…
```

By default, the first value in the Fibonacci sequence is skipped. This is so the same delay isn't repeated.

If you'd like to include it, you can pass `true` as the second parameter.

``` php
$backoff = Backoff::fibonacci(1, true); // 1, 1, 2, 3, 5, 8, 13, 21, 34, 55…
$backoff = Backoff::fibonacci(5, true); // 5, 5, 10, 15, 25, 40, 65, 105, 170, 275…
```



### Decorrelated Backoff

The decorrelated backoff strategy is a feedback loop where the previous delay is used as input to help to determine the next delay.

A random delay between the `$baseDelay` and the `previous-delay * 3` is picked.

Jitter is not applied to this strategy.

`$delay = rand($baseDelay, $prevDelay * $multiplier)`

``` php
// Backoff::random($baseDelay, $multiplier = 3);

$backoff = Backoff::decorrelated(1); // 2.6501523185, 7.4707976956, 12.3241439061, 25.1076970005, 46.598982162…
$backoff = Backoff::decorrelated(1, 2); // 1.6147780669, 2.9651922732, 5.7128698436, 10.3225378844, 2.3890401166…
```



### Random Backoff

The random backoff strategy waits for a random period of time within the range you specify.

Jitter is not applied to this strategy.

`$delay = rand($min, $max)`

``` php
// Backoff::random($min, $max);

$backoff = Backoff::random(2, 5); // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137…
```



### Sequence Backoff

The sequence backoff strategy waits for the periods of time that you specify. The attempts will stop when the delays have been exhausted.

> ***Note:*** You'll need to make sure the delays you specify match the [unit-of-measure](#unit-of-measure) being used.

`$delay = $delays[$retryNumber - 1]`

``` php
// Backoff::sequence($delays);

$backoff = Backoff::sequence([1, 1.25, 1.5, 2, 3]); // 1, 1.25, 1.5, 2, 3
```

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), one more delay will be used than the number of attempts you specify.



### Callback Backoff

The callback backoff strategy lets you specify a callback that chooses the periods to wait for.

Your callback is expected to return an `int` or `float` representing the delay, or `null` to indicate that the attempts should stop.

`$delay = $callback($retryNumber)`

``` php
// $callback = function (int $retryNumber): int|float|null …

$backoff = Backoff::callback($callback); // $callback(1), $callback(2), $callback(3), $callback(4), $callback(5)… 
```

> ***Note:*** You'll need to make sure the delays you return match the [unit-of-measure](#unit-of-measure) being used.

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), the first delay will be used before your callback is called.
> 
> When called, `$retryNumber` will start with 1, but it will really be for the second attempt onwards.



### Custom Backoff Class

As well as the callback option above, you have the ability to create your own backoff strategy by implementing the `BackoffStrategyInterface`.

``` php
// MyBackoffStrategy.php

use CodeDistortion\Backoff\Support\BaseBackoffStrategy;
use CodeDistortion\Backoff\Support\BackoffStrategyInterface;

class MyBackoffStrategy extends BaseBackoffStrategy implements BackoffStrategyInterface
{
    public function __construct(
        // private int|float $initialDelay,
        // … and any other parameters you need
    ) {
    }
    
    public function calculateBackoffDelay(int $retryNumber): int|float|null
    {
        return … // your logic here 
    }
}
```

You can then use your custom backoff strategy like this:

``` php
$strategy = new MyBackoffStrategy(…);
$backoff = Backoff::custom($strategy);
```

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), the first delay will be used before `calculateBackoffDelay()` is called.
>
> When called, `$retryNumber` will start with 1, but it will really be for the second attempt onwards.



### Noop Backoff

The noop backoff strategy doesn't wait at all, retries are attempted straight away.

It might be useful for testing purposes.

``` php
$backoff = Backoff::noop(); // 0, 0, 0, 0, 0… 
```



### No Backoff

The no backoff strategy doesn't allow retries at all. It might be useful for testing purposes.

``` php
$backoff = Backoff::none(); // (no retries) 
```



## Jitter

Jitter is the concept of making random adjustments to the backoff delays.

Having a backoff strategy alone may not be enough to prevent a large number of clients from retrying at the same moments in time. Jitter is used to help mitigate this.

First, the backoff strategy is used to calculate the desired delay, and then jitter is applied to it.

For example, if the backoff strategy generates a delay of 100ms, the jitter could adjust this to somewhere between 75ms and 125ms. The actual range is determined by the type of jitter used. 



### Full Jitter

Full Jitter applies a random adjustment to the delay, within the range of 0 and the *full delay*. i.e. between 0% and 100% of the delay.

`$delay = rand(0, $delay)`

``` php
$backoff = Backoff::exponential(1)
    ->fullJitter() // <<< between 0% and 100%
    ->maxDelay(200)
    ->maxAttempts(10);
```



### Equal Jitter

Equal Jitter applies a random adjustment to the delay, within the range of *half* and the *full delay*. i.e. between 50% and 100% of the delay.

`$delay = rand($delay / 2, $delay)`

``` php
$backoff = Backoff::exponential(1)
    ->equalJitter() // <<< between 50% and 100%
    ->maxDelay(200)
    ->maxAttempts(10);
```



### Custom Jitter Range

If you'd like a different range compared to full and equal jitter above, jitter-range lets you specify your own custom range.

`$delay = rand($delay * $min, $delay  * $max)`

``` php
$backoff = Backoff::exponential(1)
    ->jitterRange(0.5, 1.5) // <<< between 50% and 150%
    ->maxDelay(200)
    ->maxAttempts(10);
```



### Jitter Callback

Jitter callback lets you specify a callback that applies jitter to the delay.

Your callback is expected to return an `int` or `float` representing the updated delay.

`$delay = $callback($delay)`

``` php
// $callback = function(int|float $delay): int|float …

$backoff = Backoff::exponential(1)
    ->jitterCallback($callback) // <<<
    ->maxDelay(200)
    ->maxAttempts(10);
```



### Custom Jitter Class

As well as customising jitter using the range and callback options above, you have the ability to create your own jitter class by implementing the `JitterInterface`.

``` php
// MyJitter.php

use CodeDistortion\Backoff\Support\BaseJitter;
use CodeDistortion\Backoff\Support\JitterInterface;

class MyJitter extends BaseJitter implements JitterInterface
{
    public function __construct(
        // … any parameters you need
    ) {
    }
    
    public function apply(int|float $delay): int|float
    {
        return … // your logic here 
    }
}
```

You can then use your custom jitter class like this:

``` php
$jitter = new MyJitter(…);
$backoff = Backoff::exponential(10)->customJitter($jitter);
```



## Under-the-hood ??? [ todo ]

Each of the backoff strategies are actually classes that implement the `BackoffStrategyInterface`. These are what are used by Backoff. The instantiation methods above like ::linear() and ::exponential() are just shortcuts to make it easier to create them.


``` php
$strategy = new LinearBackoffStrategy(5, 10);
$jitter = new FullJitter();
$backoff = Backoff::new(
    $strategy,
    $jitter,
    $maxAttempts,
    $maxDelay,
    $unitType,
    $runsBeforeFirstAttempt
);
```

``` php
$strategy = new ExponentialBackoffStrategy(1, 1.5);
$backoff = Backoff::new($strategy);
...
```

``` php
$jitter = new FullJitter();
$backoff->customJitter($jitter);
...
```

``` php
$backoff->unit(Settings::UNIT_MILLISECONDS);
```



## Logging

Backoff collects information about each backoff attempt as it goes.

The current attempt is available as you go through the loop, as well as full list.

The logs are made up of `AttemptLog` objects and you can use these if you'd like to perform logging or analysis yourself.

``` php
$backoff = Backoff::exponential(10)->unitMs()->maxAttempts(10);
do {
    $success = …; // do some work
    $log = $backoff->latestLog(); // returns the current AttemptLog
} while ((!$success) && ($backoff->step()));

$backoff->logs(); // returns all the AttemptLogs in an array
```

`AttemptLog` contains the following methods:

``` php
$log->attemptNumber(); // the attempt number current at the time
$log->maxPossibleAttempts(); // null for infinity
// note: a BackoffStrategy can return null to end the attempts
// early which won't be reflected here

$log->firstAttemptOccurredAt(); // when the first attempt started
$log->thisAttemptOccurredAt(); // when the current attempt started

// the delay applied before this attempt
// (will be null for the first attempt)
$log->delay(); // in the current unit-of-measure
$log->delayInSeconds(); // in seconds
$log->delayInMs(); // in milliseconds
$log->delayInUs(); // in microseconds

// the time spent attempting the action this time
// (will only be known if there is a further attempt)
$log->workingTime(); // in the current unit-of-measure
$log->workingTimeInSeconds(); // in seconds
$log->workingTimeInMs(); // in milliseconds
$log->workingTimeInUs(); // in microseconds

// the overall delay
// (sum of all delays)
$log->overallDelay(); // in the current unit-of-measure
$log->overallDelayInSeconds(); // in seconds
$log->overallDelayInMs(); // in milliseconds
$log->overallDelayInUs(); // in microseconds

// the overall time spent attempting the action
// (sum of all working time)
$log->overallWorkingTime(); // in the current unit-of-measure
$log->overallWorkingTimeInSeconds(); // in seconds
$log->overallWorkingTimeInMs(); // in milliseconds
$log->overallWorkingTimeInUs(); // in microseconds

$log->unitType(); // the unit-of-measure used
```

If you're using `$backoff->step()` at the end of your loop, the first attempt's start-time will be based on when `$backoff` was created. If you'd like to record the start as being closer to when the loop starts, you can call `->reset()`.

``` php
$backoff = Backoff::exponential(10)->unitMs()->maxAttempts(10);

// … something that takes a while

$backoff->reset(); // <<< will reset the start-time
do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));

$backoff->getLogs();
```



## Backoff When Running Tests

When running your test-suite, you might want to disable backoff delays, or limit the number of allowed attempts.



### Disabling Backoff

You can remove the delay between attempts using `->onlyDelayWhen(false)`.

The action may still be retried, but there won't be any delays in between attempts.

``` php
$runningTests = …;

$backoff = Backoff::exponential(10)
    ->unitMs()
    ->fullJitter()
    ->maxDelay(200)
    ->maxAttempts(10)
    // 0, 0, 0, 0, 0… backoff delays when running tests
    ->onlyDelayWhen(!$runningTests); // <<<

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```

When `$runningTests` is `true`, this is:
- equivalent to setting `->maxDelay(0)`, and
- largely equivalent to using the `Backoff::noop()` backoff strategy.



### Disabling Retries

Alternatively, you can disable retries altogether using `->onlyRetryWhen(false)`.

``` php
$runningTests = …;

$backoff = Backoff::exponential(10)
    ->unitMs()
    ->fullJitter()
    ->maxDelay(200)
    ->maxAttempts(10)
    // no reties when running tests
    ->onlyRetryWhen(!$runningTests); // <<<

do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```

When `$runningTests` is `true`, this is equivalent to:
- setting `->maxAttempts(1)`, or
- using the `Backoff::none()` backoff strategy.



## Testing This Package

- Clone this package: `git clone https://github.com/code-distortion/backoff.git .`
- Run `composer install` to install dependencies
- Run the tests: `composer test`



## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.



### SemVer

This library uses [SemVer 2.0.0](https://semver.org/) versioning. This means that changes to `X` indicate a breaking change: `0.0.X`, `0.X.y`, `X.y.z`. When this library changes to version 1.0.0, 2.0.0 and so forth, it doesn't indicate that it's necessarily a notable release, it simply indicates that the changes were breaking.



## Treeware

This package is [Treeware](https://treeware.earth). If you use it in production, then we ask that you [**buy the world a tree**](https://plant.treeware.earth/code-distortion/backoff) to thank us for our work. By contributing to the Treeware forest you’ll be creating employment for local families and restoring wildlife habitats.



## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.



### Code of Conduct

Please see [CODE_OF_CONDUCT](.github/CODE_OF_CONDUCT.md) for details.



### Security

If you discover any security related issues, please email tim@code-distortion.net instead of using the issue tracker.



## Credits

- [Tim Chandler](https://github.com/code-distortion)



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
