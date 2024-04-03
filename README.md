# Backoff Already

[![Latest Version on Packagist](https://img.shields.io/packagist/v/code-distortion/backoff.svg?style=flat-square)](https://packagist.org/packages/code-distortion/backoff)
![PHP Version](https://img.shields.io/badge/PHP-8.0%20to%208.3-blue?style=flat-square)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/code-distortion/backoff/run-tests.yml?branch=main&style=flat-square)](https://github.com/code-distortion/backoff/actions)
[![Buy The World a Tree](https://img.shields.io/badge/treeware-%F0%9F%8C%B3-lightgreen?style=flat-square)](https://plant.treeware.earth/code-distortion/backoff)
[![Contributor Covenant](https://img.shields.io/badge/contributor%20covenant-v2.1%20adopted-ff69b4.svg?style=flat-square)](.github/CODE_OF_CONDUCT.md)

***code-distortion/backoff*** is a PHP Library that provides retries and backoff delays when actions fail.

It's useful when you're working with services that might be temporarily unavailable, such as APIs.

```php
// let Backoff manage the retries and delays for you
$result = Backoff::exponential(2)->maxAttempts(10)->maxDelay(30)->attempt($action);
```

> See the [cheatsheet](#cheatsheet) for an overview of what's possible.



## Table of Contents

- [Installation](#installation)
- [General Tips](#general-tips)
  - [Further Reading](#further-reading)
- [Cheatsheet](#cheatsheet)
- [Usage](#usage)
  - [Use Backoff to Manage the Retry Process](#use-backoff-to-manage-the-retry-process)
  - [Or Manage The Retry Loop Yourself](#or-manage-the-retry-loop-yourself)
- [Backoff Algorithms](#backoff-algorithms)
  - [Fixed Backoff](#fixed-backoff)
  - [Linear Backoff](#linear-backoff)
  - [Exponential Backoff](#exponential-backoff)
  - [Polynomial Backoff](#polynomial-backoff)
  - [Fibonacci Backoff](#fibonacci-backoff)
  - [Decorrelated Backoff](#decorrelated-backoff)
  - [Random Backoff](#random-backoff)
  - [Sequence Backoff](#sequence-backoff)
  - [Callback Backoff](#callback-backoff)
  - [Custom Backoff Algorithm Class](#custom-backoff-algorithm-class)
  - [Noop Backoff](#noop-backoff)
  - [No Backoff](#no-backoff)
- [Configuration](#configuration)
  - [Max Attempts](#max-attempts)
  - [Delay](#delay)
    - [Max-Delay](#max-delay)
    - [Immediate First Retry](#immediate-first-retry)
  - [Jitter](#jitter)
    - [Full Jitter](#full-jitter)
    - [Equal Jitter](#equal-jitter)
    - [Custom Jitter Range](#custom-jitter-range)
    - [Jitter Callback](#jitter-callback)
    - [Custom Jitter Class](#custom-jitter-class)
    - [No Jitter](#no-jitter)
- [Managing Exceptions](#managing-exceptions)
  - [Retry When Any Exception Occurs](#retry-when-any-exception-occurs)
  - [Retry When Particular Exceptions Occur](#retry-when-particular-exceptions-occur)
  - [Don't Retry When Exceptions Occur](#dont-retry-when-exceptions-occur)
- [Managing "Invalid" Return Values](#managing-invalid-return-values)
  - [Retry When](#retry-when) 
  - [Retry Until](#retry-until)
- [Callbacks](#callbacks)
  - [Exception Callback](#exception-callback)
  - [Invalid Result Callback](#invalid-result-callback)
  - [Success Callback](#success-callback)
  - [Failure Callback](#failure-callback)
  - [Finally Callback](#finally-callback)
- [Logging](#logging)
  - [The AttemptLog Class](#the-attemptlog-class)
- [Backoff and Test Suites](#backoff-and-test-suites)
  - [Disabling Backoff](#disabling-backoff)
  - [Disabling Retries](#disabling-retries)
- [Modelling](#modelling)



## Installation

Install the package via composer:

```bash
composer require code-distortion/backoff
```



## General Tips

- Backoff attempts are intended to be used when actions fail because of **transient** issues (such as temporary service outages). When **permanent** errors occur (such as a 404 HTTP response), [retrying should stop](#managing-exceptions) as it won't help.
- Be careful when nesting backoff attempts. This can unexpectedly increase the number of attempts and time taken.
- Actions taken during backoff attempts should be idempotent. Meaning, if the same action is performed multiple times, the result should be the same as if it were only performed once.



### Further Reading

- The article [Timeouts, retries, and backoff with jitter](https://aws.amazon.com/builders-library/timeouts-retries-and-backoff-with-jitter/) by Marc Brooker at AWS does a good job of explaining the concepts involved when using backoff.
- The article [Exponential Backoff And Jitter](https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/) also by Marc Brooker is a good read if you're interested in the theory behind backoff algorithms and jitter. Marc [explains the same concepts in a 2019 talk](https://www.youtube.com/watch?v=sKRdemSirDM&t=1896s).



## Cheatsheet

```php
// usual case
$action = fn() => …; // do some work
$result = Backoff::exponential(2)->maxAttempts(10)->maxDelay(30)->attempt($action);

// selection of examples
$result = Backoff::exponential(2)->attempt($action, $default);
Backoff::exponential(2)->equalJitter()->immediateFirstRetry()->attempt($action);
Backoff::exponential(2)->retryExceptions(MyException::class)->attempt($action);
Backoff::exponential(2)->retryWhen(false)->attempt($action);
Backoff::exponential(2)->failureCallback($failed)->attempt($action);
```

Start by picking a backoff algorithm to use…

```php
// backoff algorithms - in seconds
Backoff::fixed(2)                       // 2, 2, 2, 2, 2…
Backoff::linear(5)                      // 5, 10, 15, 20, 25…
Backoff::linear(5, 10)                  // 5, 15, 25, 35, 45…
Backoff::exponential(1)                 // 1, 2, 4, 8, 16…
Backoff::exponential(1, 1.5)            // 1, 1.5, 2.25, 3.375, 5.0625…
Backoff::polynomial(1)                  // 1, 4, 9, 16, 25…
Backoff::polynomial(1, 1.5)             // 1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…
Backoff::fibonacci(1)                   // 1, 1, 2, 3, 5…
Backoff::decorrelated(1)                // 1.6147780669, 2.9651922732, 5.7128698436, 10.3225378844, 2.3890401166…
Backoff::random(2, 5)                   // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137…
Backoff::sequence([1, 2, 3, 5, 10])     // 1, 2, 3, 5, 10
Backoff::sequence([1, 2, 3, 5, 10], 15) // 1, 2, 3, 5, 10, 15, 15, 15, 15, 15…
Backoff::callback($callback)            // $callback(1, $prev), $callback(2, $prev), $callback(3, $prev)…
Backoff::custom($backoffAlgorithm)      // delay managed by a custom backoff algorithm class

// backoff algorithms - in milliseconds
Backoff::fixedMs(2)                       // 2, 2, 2, 2, 2…
Backoff::linearMs(5)                      // 5, 10, 15, 20, 25…
Backoff::linearMs(5, 10)                  // 5, 15, 25, 35, 45…
Backoff::exponentialMs(1)                 // 1, 2, 4, 8, 16…
Backoff::exponentialMs(1, 1.5)            // 1, 1.5, 2.25, 3.375, 5.0625…
Backoff::polynomialMs(1)                  // 1, 4, 9, 16, 25…
Backoff::polynomialMs(1, 1.5)             // 1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…
Backoff::fibonacciMs(1)                   // 1, 1, 2, 3, 5…
Backoff::decorrelatedMs(1)                // 1.6147780669, 2.9651922732, 5.7128698436, 10.3225378844, 2.3890401166…
Backoff::randomMs(2, 5)                   // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137…
Backoff::sequenceMs([1, 2, 3, 5, 10])     // 1, 2, 3, 5, 10
Backoff::sequenceMs([1, 2, 3, 5, 10], 15) // 1, 2, 3, 5, 10, 15, 15, 15, 15, 15…
Backoff::callbackMs($callback)            // $callback(1, $prev), $callback(2, $prev), $callback(3, $prev)…
Backoff::customMs($backoffAlgorithm)      // delay managed by a custom backoff algorithm class

// backoff algorithms - in microseconds
Backoff::fixedUs(2)                       // 2, 2, 2, 2, 2…
Backoff::linearUs(5)                      // 5, 10, 15, 20, 25…
Backoff::linearUs(5, 10)                  // 5, 15, 25, 35, 45…
Backoff::exponentialUs(1)                 // 1, 2, 4, 8, 16…
Backoff::exponentialUs(1, 1.5)            // 1, 1.5, 2.25, 3.375, 5.0625…
Backoff::polynomialUs(1)                  // 1, 4, 9, 16, 25…
Backoff::polynomialUs(1, 1.5)             // 1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…
Backoff::fibonacciUs(1)                   // 1, 1, 2, 3, 5…
Backoff::decorrelatedUs(1)                // 1.6147780669, 2.9651922732, 5.7128698436, 10.3225378844, 2.3890401166…
Backoff::randomUs(2, 5)                   // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137…
Backoff::sequenceUs([1, 2, 3, 5, 10])     // 1, 2, 3, 5, 10
Backoff::sequenceUs([1, 2, 3, 5, 10], 15) // 1, 2, 3, 5, 10, 15, 15, 15, 15, 15…
Backoff::callbackUs($callback)            // $callback(1, $prev), $callback(2, $prev), $callback(3, $prev)…
Backoff::customUs($backoffAlgorithm)      // delay managed by a custom backoff algorithm class

// utility backoff algorithms
Backoff::noop() // 0, 0, 0, 0, 0…
Backoff::none() // (no retries)
```

Then apply customisation if needed…

```php
// max-attempts (default = no limit)
->maxAttempts(10)   // the maximum number of attempts allowed
->maxAttempts(null) // remove the limit, or
->noAttemptLimit()  // remove the limit, or
->noMaxAttempts()   // alias for noAttemptLimit()

// max-delay - the maximum delay to wait between each attempt (default = no limit)
->maxDelay(30)   // set the max-delay, in the current unit-of-measure
->maxDelay(null) // remove the limit, or
->noDelayLimit() // remove the limit, or
->noMaxDelay()   // alias for noDelayLimit()

// choose the type of jitter to apply to the delay (default = full jitter)
->fullJitter()              // apply full jitter, between 0 and 100% of the delay (applied by default)
->equalJitter()             // apply equal jitter, between 50% and 100% of the delay
->jitterRange(0.75, 1.25)   // apply jitter between $min and $max (e.g. 0.75 = 75%, 1.25 = 125%)
->jitterCallback($callback) // specify a callback that applies the jitter
->customJitter($jitter)     // jitter managed by a custom jitter class
->noJitter()                // disable jitter

// insert an initial retry that happens straight away
// before the backoff algorithm starts (default = off)
->immediateFirstRetry()      // insert an immediate retry
->immediateFirstRetry(false) // don't insert an immediate retry, or
->noImmediateFirstRetry()    // don't insert an immediate retry

// turn off delays or retries altogether - useful when running tests (default = enabled)
->onlyDelayWhen(!$runningTests) // enable or disable delays (disabled means delays are 0)
->onlyRetryWhen(!$runningTests) // enable or disable retries (disabled means only 1 attempt will be made)
```

Here is some customisation to the retry logic you can use when [using the `->attempt()` method](#use-backoff-to-manage-the-retry-process)…

```php
// retries based on exceptions…

// retry when any exception occurs (default)
// if default is omitted, the final exception will be rethrown
->retryAllExceptions($default = null)

// retry when these exceptions occur in particular,
// along with a default value to return if all attempts fail
// (you can specify multiple types of exceptions by passing
// them as an array, or by calling this multiple times)
->retryExceptions(MyException::class, $default)
// you can also pass a callback that chooses whether to retry or not
// (return true to retry, false to end)
// $callback(Throwable $e, AttemptLog $log): bool
->retryExceptions($callback, $default);
// or choose not to retry when exceptions occur
->retryExceptions(false) // or
->dontRetryExceptions()
```

```php
// retries based on the return value…
// (default = don't retry based on return values)

// retry WHEN this value is returned,
// whether to use strict comparison or not,
// and a default value to return if all attempts fail
// (you can call this multiple times to add different values)
->retryWhen($match, $strict = false, $default = null)
// you can also pass a callback that chooses whether to retry or not
// (return true to retry, false to end)
// $callback(mixed $result, AttemptLog $log): bool
->retryWhen($callback)

// retry UNTIL this value is returned,
// and whether to use strict comparison or not
// (you can call this multiple times to add different values)
->retryUntil($match, $strict = false)
// you can also pass a callback that chooses whether to retry or not
// (unlike ->retryWhen(…), here you return false to retry, true to end)
->retryUntil($callback) // $callback(mixed $result, AttemptLog $log): bool
```

```php
// callbacks…
// (you can specify multiple at a time by passing them as an array,
// or by calling these methods multiple times)

// called when any exception occurs
// $callback(Throwable $e, AttemptLog $log, bool $willRetry): void
->exceptionCallback($callback)

// called when an "invalid" value is returned
// $callback(mixed $result, AttemptLog $log): void
->invalidResultCallback($callback)

// called after the attempt succeeds
// $callback(AttemptLog[] $logs): void
->successCallback($callback)

// called after all attempts fail, including when no
// attempts occur, and when an exception is thrown
// $callback(AttemptLog[] $logs): void
->failureCallback($callback)

// called afterwards regardless of the outcome, including
// when no attempts occur, and when an exception is thrown
// $callback(AttemptLog[] $logs): void
->finallyCallback($callback)
```

Last, use it to run your work…

```php
->attempt($action);           // run your callback and retry it when needed 
->attempt($action, $default); // run your callback, retry it when needed, and return $default if all attempts fail
```

You don't need to use these methods,  but if they'll help you if you'd like to [manage the retry loop yourself](#or-manage-the-retry-loop-yourself)…

```php
// tell backoff where you're placing the call to ->step() (default = afterwards)
->runsAtStartOfLoop()      // specify that $backoff->step() will be called at the entrance to your loop
->runsAtStartOfLoop(false) // specify that $backoff->step() will be called at the end of your loop (default), or
->runsAtEndOfLoop()        // specify that $backoff->step() will be called at the end of your loop (default)

->step(); // calculate and perform the delay, return false when the attempts are exhausted

// or, you can perform the steps that ->step() does, yourself
->calculate(); // calculate the next delay
->sleep();     // sleep for the delay calculated by ->calculate()

// if you'd like to perform the sleep yourself instead of calling ->sleep()
->getDelay();          // get the delay in the current unit-of-measure
->getDelayInSeconds(); // get the delay in seconds (note: may contain decimals)
->getDelayInMs();      // get the delay in milliseconds (note: may contain decimals)
->getDelayInUs();      // get the delay in microseconds (note: may contain decimals)
->getUnitType();       // get the unit-of-measure being used
                       // these are values from CodeDistortion\Backoff\Settings::UNIT_XXX

// helpers
->currentAttemptNumber(); // get the current attempt number
->isFirstAttempt();       // check if the first attempt is currently being made
->isLastAttempt();        // check if the last attempt is currently being made (however it may run indefinitely)
->hasStopped();           // check if the attempts have been exhausted - this is triggered by ->calculate()
->currentLog();           // get the AttemptLog for the current attempt
->logs();                 // get the AttemptLogs for all of the attempts 
->reset();                // reset the backoff to its initial state, ready to start again
```



## Usage

### Use Backoff to Manage the Retry Process

Backoff can manage the retry process for you. It retries when exceptions occur (and/or when invalid results are returned [when configured to](#managing-invalid-return-values)), so you don't need to worry about the logic. Just pass your callable `$action` to `->attempt()`, and it will handle the rest.

Start by picking a [backoff algorithm](#backoff-algorithms) to use, [configure it as needed](#configuration), and then use it to run your work.

```php
use CodeDistortion\Backoff\Backoff;

$action = fn() => …; // do some work
$result = Backoff::exponential(1)->maxDelay(30)->maxAttempts(10)->attempt($action);
```

See below for more [configuration options](#configuration).

By default, the final exception is rethrown, but you can pass a default value to be returned instead if all attempts fail.

```php
$result = Backoff::exponential(1)->maxDelay(30)->maxAttempts(10)->attempt($action, $default);
```

See below for more details about [managing exceptions](#managing-exceptions) and ["invalid" return values](#managing-invalid-return-values).



### Or Manage the Retry Loop Yourself

If you'd like more control over the process, you can manage the retry loop yourself. This involves setting up a loop and using Backoff to handle the delays.

> This option is more detailed to implement, and probably doesn't offer any more flexibility than [the other option](#use-backoff-to-manage-the-retry-process), so I've hidden the details below. But by all means, have a look and use them if they suit your needs.

<details><summary>(Click here for loop examples)</summary>
<p>

> ***Note:***
>
> - If you'd like Backoff to catch *particular* exceptions, you can use [->retryExceptions(…)](#managing-exceptions). This lets you specify which exceptions to retry, or specify a callback to make the decision.
> - If you'd like to selectively retry based on particular *return values*, you can use [->retryWhen(…)](#retry-when) or [->retryUntil(…)](#retry-until). These let you specify values to check for, or specify a callback to make the decision.
> - If you'd like to perform tasks before each retry delay (like logging), you could consider using [->exceptionCallback(…)](#exception-callback) or [->invalidResultCallback(…)](#invalid-result-callback).



#### Basic Loop

Start by picking a [backoff algorithm](#backoff-algorithms) to use, [configure it as needed](#configuration). Then incorporate it into your loop.

`$backoff->step()` is the method you will call to trigger the backoff logic. It will wait the appropriate amount of time based on how many attempts have been made, and return `false` when the attempts have been exhausted.

```php
use CodeDistortion\Backoff\Backoff;

// choose a backoff algorithm and configure it as needed
$backoff = Backoff::exponential(1)->maxDelay(30)->maxAttempts(10);

// then use it in your loop
do {
    $success = …; // do some work
} while ((!$success) && ($backoff->step()));
```

If you'd like to attempt your action *zero* or more times, you can place `$backoff->step()` at the *entrance* of your loop, having called `->runsAtStartOfLoop()` beforehand.

This lets Backoff know, so it doesn't perform the delay and count the attempt the first time.

```php
$maxAttempts = …; // possibly 0

// specify that $backoff->step() will be called at the entrance to your loop
$backoff = Backoff::exponential(1)->maxDelay(30)->maxAttempts($maxAttempts)->runsAtStartOfLoop();

$success = false;
while ((!$success) && ($backoff->step())) {
    $success = …; // do some work
};
```



#### Catching Exceptions in Your Loop

```php
$maxAttempts = …; // possibly 0
$backoff = Backoff::exponential(1)->maxDelay(30)->maxAttempts($maxAttempts)->runsAtStartOfLoop();

$success = false;
while ((!$success) && ($backoff->step())) {
    try {
        $success = …; // do some work
    } catch (MyException $e) {
        // handle the exception
    }
};
```



#### Deconstruction of the Backoff Logic

Behind the scenes, `->step()` calls `->calculate()` to determine the delay, and then `->sleep()` to wait for that delay. You can call these yourself directly instead.

Both `->calculate()` and `->sleep()` both return `false` when the attempts have been exhausted.

`->sleep()` will only sleep when there is a delay greater than zero to be made.

```php
$backoff = Backoff::exponential(1)->maxDelay(30)->maxAttempts(10)->runsAtStartOfLoop();

do {
    // calculate the next delay
    if (!$backoff->calculate()) {
        break;
    }
    // sleep for the calculated delay period
    // (will skip the first sleep because of ->runsAtStartOfLoop())
    $backoff->sleep();

    $success = …; // do some work

} while (!$success);
```

You could choose for Backoff to calculate the delay, and then perform the sleep yourself.

```php
$backoff = Backoff::exponential(1)->maxDelay(30)->maxAttempts(10)->runsAtStartOfLoop();

do {
    if (!$backoff->calculate()) {
        break;
    }
    $usleep = $backoff->getDelayInUs();
    if ($usleep > 0) {
        usleep((int) $usleep);
    }

    $success = …; // do some work

} while (!$success);
```



#### Helpers
 
There are some helpers you can use to help you manage the process.

```php
$backoff->currentAttemptNumber() // get the current attempt number
$backoff->isFirstAttempt()       // check if the first attempt is currently being made
$backoff->isLastAttempt()        // check if the last attempt is currently being made (however it may run indefinitely)
$backoff->hasStopped()           // check if the attempts have been exhausted - this is triggered by ->calculate()
$backoff->currentLog()           // get the AttemptLog for the current attempt *
$backoff->logs()                 // get the AttemptLogs for all of the attempts *
$backoff->reset()                // reset the backoff to its initial state, ready to start again
```

\* See below for [information about logging](#logging).

</p>
</details>



## Backoff Algorithms

Backoff algorithms are used to determine how long to wait between attempts. They usually increase the delay between attempts in some way.

They generate the "base" delay for each attempt. [Jitter](#jitter) can be applied afterwards to make them less predictable.

By default, delays are in seconds. However, each algorithm has a millisecond and microsecond option.

> ***Note:*** Delays in any unit-of-measure can have decimal places, including seconds.

> ***Note:*** Microseconds are probably small enough that the numbers start to become inaccurate because of PHP overheads when sleeping. For example, on my computer, while code can run quicker than a microsecond, running usleep(1) to sleep for 1 microsecond actually takes about 55 microseconds.

Several backoff algorithms have been included to choose from, and you can also [create your own](#custom-backoff-algorithm-class)…



### Fixed Backoff

The fixed backoff algorithm waits the *same* amount of time between each attempt.

```php
// Backoff::fixed($delay)

Backoff::fixed(2)->attempt($action); // 2, 2, 2, 2, 2…

Backoff::fixedMs(2)->attempt($action); // in milliseconds
Backoff::fixedUs(2)->attempt($action); // in microseconds
```



### Linear Backoff

The linear backoff algorithm increases the waiting period by a specific amount each time.

The amount to increase by defaults to `$initialDelay` when not set.

`Logic: $delay = $initialDelay + (($retryNumber - 1) * $delayIncrease)`

```php
// Backoff::linear($initalDelay, $delayIncrease = null)

Backoff::linear(5)->attempt($action);     // 5, 10, 15, 20, 25…
Backoff::linear(5, 10)->attempt($action); // 5, 15, 25, 35, 45…

Backoff::linearMs(5)->attempt($action); // in milliseconds
Backoff::linearUs(5)->attempt($action); // in microseconds
```



### Exponential Backoff

The exponential backoff algorithm increases the waiting period exponentially.

By default, the delay is doubled each time, but you can change the factor it multiplies by.

`Logic: $delay = $initialDelay * pow($factor, $retryNumber - 1)`

```php
// Backoff::exponential($initalDelay, $factor = 2)

Backoff::exponential(1)->attempt($action);      // 1, 2, 4, 8, 16…
Backoff::exponential(1, 1.5)->attempt($action); // 1, 1.5, 2.25, 3.375, 5.0625…

Backoff::exponentialMs(1)->attempt($action); // in milliseconds
Backoff::exponentialUs(1)->attempt($action); // in microseconds
```



### Polynomial Backoff

The polynomial backoff algorithm increases the waiting period in a polynomial manner.

By default, the retry number is raised to the power of 2, but you can change this.

`Logic: $delay = $initialDelay * pow($retryNumber, $power)`

```php
// Backoff::polynomial($initialDelay, $power = 2)

Backoff::polynomial(1)->attempt($action);      // 1, 4, 9, 16, 25…
Backoff::polynomial(1, 1.5)->attempt($action); // 1, 2.8284271247462, 5.1961524227066, 8, 11.180339887499…

Backoff::polynomialMs(1)->attempt($action); // in milliseconds
Backoff::polynomialUs(1)->attempt($action); // in microseconds
```



### Fibonacci Backoff

The Fibonacci backoff algorithm increases waiting period by following a Fibonacci sequence. This is where each delay is the sum of the previous two delays.

`Logic: $delay = $previousDelay1 + $previousDelay2`

```php
// Backoff::fibonacci($initialDelay, $includeFirst = false)

Backoff::fibonacci(1)->attempt($action); // 1, 2, 3, 5, 8, 13, 21, 34, 55, 89…
Backoff::fibonacci(5)->attempt($action); // 5, 10, 15, 25, 40, 65, 105, 170, 275, 445…

Backoff::fibonacciMs(1)->attempt($action); // in milliseconds
Backoff::fibonacciUs(1)->attempt($action); // in microseconds
```

By default, the first value in the Fibonacci sequence is skipped. This is so the same delay isn't repeated.

If you'd like to include it, you can pass `true` as the second parameter.

```php
Backoff::fibonacci(1, false)->attempt($action); // 1, 2, 3, 5, 8, 13, 21, 34, 55, 89…
Backoff::fibonacci(1, true)->attempt($action); // 1, 1, 2, 3, 5, 8, 13, 21, 34, 55…
Backoff::fibonacci(5, true)->attempt($action); // 5, 5, 10, 15, 25, 40, 65, 105, 170, 275…
```



### Decorrelated Backoff

The decorrelated backoff algorithm is a feedback loop where the previous delay is used as input to help to determine the next delay.

A random delay between the `$baseDelay` and the `previous-delay * 3` is picked.

Jitter is not applied to this algorithm.

`Logic: $delay = rand($baseDelay, $prevDelay * $multiplier)`

```php
// Backoff::random($baseDelay, $multiplier = 3)

Backoff::decorrelated(1)->attempt($action); // 2.6501523185, 7.4707976956, 12.3241439061, 25.1076970005, 46.598982162…
Backoff::decorrelated(1, 2)->attempt($action); // 1.6147780669, 2.9651922732, 5.7128698436, 10.3225378844, 2.3890401166…

Backoff::decorrelatedMs(1)->attempt($action); // in milliseconds
Backoff::decorrelatedUs(1)->attempt($action); // in microseconds
```



### Random Backoff

The random backoff algorithm waits for a random period of time within the range you specify.

Jitter is not applied to this algorithm.

`Logic: $delay = rand($min, $max)`

```php
// Backoff::random($min, $max)

Backoff::random(2, 5)->attempt($action); // 2.7361497528, 2.8163467878, 4.6468904857, 3.3016198676, 3.3810068137…

Backoff::randomMs(2, 5)->attempt($action); // in milliseconds
Backoff::randomUs(2, 5)->attempt($action); // in microseconds
```



### Sequence Backoff

The sequence backoff algorithm lets you specify the particular delays to use.

An optional fixed delay can be used to continue on with, after the sequence finishes. Otherwise, the attempts will stop when the delays have been exhausted.

> ***Note:*** You'll need to make sure the delay values you specify match the unit-of-measure being used.

`Logic: $delay = $delays[$retryNumber - 1]`

```php
// Backoff::sequence($delays, $continuation = null)

Backoff::sequence([1, 1.25, 1.5, 2, 3])->attempt($action); // 1, 1.25, 1.5, 2, 3
Backoff::sequence([1, 1.25, 1.5, 2, 3], 5)->attempt($action); // 1, 1.25, 1.5, 2, 3, 5, 5, 5, 5, 5…

Backoff::sequenceMs([1, 1.25, 1.5, 2, 3])->attempt($action); // in milliseconds
Backoff::sequenceUs([1, 1.25, 1.5, 2, 3])->attempt($action); // in microseconds
```

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), one more retry will be made than the number of attempts in your sequence.



### Callback Backoff

The callback backoff algorithm lets you specify a callback that chooses the waiting period.

Your callback is expected to return an `int` or `float` representing the delay, or `null` to indicate that the attempts should stop.

`Logic: $delay = $callback($retryNumber)`

```php
// $callback = function (int $retryNumber, int|float|null $prevBaseDelay): int|float|null …

Backoff::callback($callback)->attempt($action); // $callback(1, $prev), $callback(2, $prev), $callback(3, $prev)… 

Backoff::callbackMs($callback)->attempt($action); // in milliseconds
Backoff::callbackUs($callback)->attempt($action); // in microseconds
```

> ***Note:*** You'll need to make sure the delay values you return match the unit-of-measure being used.

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), the first retry will be made before delays from your callback are used.
> 
> In this case, `$retryNumber` will start with 1, but it will really be for the second attempt onwards.



### Custom Backoff Algorithm Class

As well as the [callback option above](#callback-backoff), you have the ability to create your own backoff algorithm class by implementing the `BackoffAlgorithmInterface`.

```php
// MyBackoffAlgorithm.php

use CodeDistortion\Backoff\Interfaces\BackoffAlgorithmInterface;
use CodeDistortion\Backoff\Support\BaseBackoffAlgorithm;

class MyBackoffAlgorithm extends BaseBackoffAlgorithm implements BackoffAlgorithmInterface
{
    /** @var boolean Whether jitter may be applied to the delays calculated by this algorithm. */
    public bool $jitterMayBeApplied = true;
    
    public function __construct(
        // e.g. private int|float $initialDelay,
        // … and any other parameters you need
    ) {
    }
    
    public function calculateBaseDelay(int $retryNumber, int|float|null $prevBaseDelay): int|float|null
    {
        return …; // your logic here 
    }
}
```

Then use your custom backoff algorithm like this:

```php
$algorithm = new MyBackoffAlgorithm(…);

Backoff::custom($algorithm)->attempt($action);

Backoff::customMs($algorithm)->attempt($action); // in milliseconds
Backoff::customUs($algorithm)->attempt($action); // in microseconds
```

> ***Note:*** You'll need to make sure the delay values you return match the unit-of-measure being used.

> ***Note:*** If you [use `->immediateFirstRetry()`](#immediate-first-retry), the first retry will be made before delays from your callback are used.
>
> In this case, `$retryNumber` will start with 1, but it will really be for the second attempt onwards.



### Noop Backoff

The no-op backoff algorithm doesn't wait at all, retries are attempted straight away.

This might be useful for testing purposes. See [Backoff and Test Suites](#backoff-and-test-suites) for more options when running tests.

```php
Backoff::noop()->attempt($action); // 0, 0, 0, 0, 0… 
```



### No Backoff

The "no backoff" algorithm doesn't allow retries at all.

This might be useful for testing purposes. See [Backoff and Test Suites](#backoff-and-test-suites) for more options when running tests.

```php
Backoff::none()->attempt($action); // (no retries) 
```



## Configuration

### Max Attempts

By default, Backoff will retry forever. To stop this from happening, you can specify the maximum number of attempts allowed.

Note that the number of *attempts* will be one more than the number of *retries* that occur.

```php
Backoff::exponential(1)
    ->maxAttempts(5) // <<<
    ->attempt($action);
```



### Delay

#### Max-Delay

You can specify the maximum length each base-delay can be. This is useful for preventing the delays from becoming too long.

> ***Note:*** You'll need to make sure the max-delay you specify matches the unit-of-measure being used.

> ***Note:*** This is the maximum *base* delay. [Jitter](#jitter) may still make the delay longer (if it's [allowed to go over 100%](#custom-jitter-range)).
> 
> The idea is that this will stop multiple clients from synchronising after reaching the max-delay. This should be ok because it's still largely what you want.

```php
Backoff::exponential(10)
    ->maxDelay(200) // <<<
    ->attempt($action);
```



#### Immediate First Retry

If you'd like your first retry to occur *immediately* after the first failed attempt, you can add an initial *0* delay by calling `->immediateFirstRetry()`. This will be inserted before the normal backoff delays start.

```php
Backoff::exponential(10)
    ->maxAttempts(5)
    ->immediateFirstRetry() // <<< 0, 10, 20, 40, 80…
    ->attempt($action);
```

This won't affect the maximum attempt limit. So if you set a maximum of 5 attempts, and you use `->immediateFirstRetry()`, there will be up to 6 attempts in total.



### Jitter

Having a backoff algorithm probably isn't enough on its own to prevent a large number of clients retrying at the same moments in time. Jitter is used to help mitigate this by spreading them out.

Jitter is the concept of making random adjustments to the delays generated by the backoff algorithm.

For example, if the backoff algorithm generates a delay of 100ms, jitter could adjust this to be somewhere between 75ms and 125ms. The actual range is determined by the type of jitter used.

> The article [Exponential Backoff And Jitter](https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/) by Marc Brooker at AWS does a good job of explaining what jitter is, and the reason for its use.



#### Full Jitter

Full Jitter applies a random adjustment to the delay, within the range of 0 and the *full delay*. That is, between 0% and 100% of the delay.

This is the type of jitter that is used by default.

`$delay = rand(0, $delay)`

```php
Backoff::exponential(1)
    ->fullJitter() // <<< between 0% and 100%
    ->attempt($action);
```

> ***Note:*** This jitter type is applied by default.



#### Equal Jitter

Equal Jitter applies a random adjustment to the delay, within the range of *half* and the *full delay*. That is, between 50% and 100% of the delay.

`$delay = rand($delay / 2, $delay)`

```php
Backoff::exponential(1)
    ->equalJitter() // <<< between 50% and 100%
    ->attempt($action);
```



#### Custom Jitter Range

If you'd like a different range compared to *full* and *equal* jitter above, jitter-range lets you specify your own custom range.

`$delay = rand($delay * $min, $delay  * $max)`

```php
Backoff::exponential(1)
    ->jitterRange(0.5, 1.5) // <<< between 50% and 150%
    ->attempt($action);
```



#### Jitter Callback

Jitter callback lets you specify a callback that applies jitter to the delay.

Your callback is expected to return an `int` or `float` representing the updated delay.

`$delay = $callback($delay, $retryNumber)`

```php
// $callback = function (int|float $delay, int $retryNumber): int|float …

$callback = fn(int|float $delay, int $retryNumber): int|float => …; // your logic here

Backoff::exponential(1)
    ->jitterCallback($callback) // <<<
    ->attempt($action);
```



#### Custom Jitter Class

As well as customising jitter using the [range](#custom-jitter-range) and [callback](#jitter-callback) options above, you have the ability to create your own jitter class by implementing the `JitterInterface`.

```php
// MyJitter.php

use CodeDistortion\Backoff\Interfaces\JitterInterface;
use CodeDistortion\Backoff\Support\BaseJitter;

class MyJitter extends BaseJitter implements JitterInterface
{
    public function __construct(
        // … any configuration parameters you need
    ) {
    }
    
    public function apply(int|float $delay, int $retryNumber): int|float
    {
        return …; // your logic here 
    }
}
```

You can then use your custom jitter class like this:

```php
$jitter = new MyJitter(…);

Backoff::exponential(1)
    ->customJitter($jitter) // <<<
    ->attempt($action);
```



#### No Jitter

Jitter can be turned off by calling `->noJitter()`.

```php
Backoff::exponential(1)
    ->noJitter() // <<<
    ->attempt($action);
```



## Managing Exceptions

By default, Backoff will retry whenever an exception occurs. You can customise this behaviour using the following methods.

> ***Note:*** These methods only apply when you use the [`->attempt()` method to manage the retry process](#use-backoff-to-manage-the-retry-process).



### Retry When Any Exception Occurs

Retry *all* exceptions - this is actually the default behaviour, so you don't need to call it unless you've previously set it to something else.

```php
Backoff::exponential(1)
    ->retryAllExceptions() // <<<
    ->attempt($action);
```

By default, when all attempts have failed (e.g. when `->maxAttempts(…)` is used), the final exception is rethrown afterwards. 

You can pass a default value to return instead when that happens.

```php
Backoff::exponential(1)
    ->retryAllExceptions($default) // <<<
    ->attempt($action);
```

> ***Note:*** If you pass a *callable* default value, it will be called when the default is needed. Its return value will be returned.



### Retry When Particular Exceptions Occur

You can specify particular exception types to be caught and retried, along with the optional `$default` value to return if all attempts fail.

```php
Backoff::exponential(1)
    ->retryExceptions(MyException::class, $default) // <<<
    ->attempt($action);
```

If you'd like to specify more than one, you can pass them in an array, or call it multiple times. You can specify a different `$default` value each call.

```php
Backoff::exponential(1)
    ->retryExceptions([MyException1::class, MyException2::class], $default1) // <<<
    ->retryExceptions(MyException3::class, $default2) // <<<
    ->attempt($action);
```

You can pass a callback that chooses whether to retry or not. The exception will be passed to your callback, and it should return `true` to try again, or `false` to end.

```php
$callback = fn(Throwable $e, AttemptLog $log): bool => …; // your logic here

Backoff::exponential(1)
    ->retryExceptions($callback, $default) // <<<
    ->attempt($action);
```



### Don't Retry When Exceptions Occur

And finally, you can turn this off so retries are *not* made when exceptions occur (they will be rethrown).

```php
Backoff::exponential(1)
    ->retryExceptions(false) // <<<
    ->attempt($action);
// or
Backoff::exponential(1)
    ->dontRetryExceptions() // <<<
    ->attempt($action);
```



## Managing "Invalid" Return Values

By default, Backoff will *not* retry based on the value returned by your `$action` callback. However, you can choose to if you like.

> ***Note:*** These methods apply when you use the [`->attempt()` method to manage the retry process](#use-backoff-to-manage-the-retry-process).



### Retry When…

You can specify for retries to occur when particular values are returned, along with an optional `$default` value to return if all attempts fail.

`$strict` allows you to compare the returned value to `$value` using strict comparison (`===`).

When you don't specify a default, the final value returned by `$action` will be returned.

```php
Backoff::exponential(1)
    ->retryWhen($match, $strict = false, $default = null) // <<<
    ->attempt($action);
```

You can also pass a callback that chooses whether to retry or not. Your callback should return `true` to try again, or `false` to end.

> ***Note:*** `$strict` has no effect when using a callback.

```php
$callback = fn(mixed $result, AttemptLog $log): bool => …; // your logic here

Backoff::exponential(1)
    ->retryWhen($callback, false, $default = null) // <<<
    ->attempt($action);
```

> ***Note:*** If you pass a *callable* default value, it will be called when the default is needed. Its return value will be returned.



### Retry Until…

Conversely to `->retryWhen()`, you can specify value/s to wait for, retrying *until* they're returned.

```php
Backoff::exponential(1)
    ->retryUntil($match, $strict = false) // <<<
    ->attempt($action);
```

Similarly, `$strict` allows you to compare the returned value to `$value` using strict comparison (`===`).

You can also specify a callback that chooses whether to retry or not. Contrasting with `->retryWhen()` above, your callback should return `false` to try again, or `true` to end.

> ***Note:*** `$strict` has no effect when using a callback.

```php
$callback = fn(mixed $result, AttemptLog $log): bool => …; // your logic here

Backoff::exponential(1)
    ->retryUntil($callback) // <<<
    ->attempt($action);
```

> ***Note:*** If you pass a *callable* default value, it will be called when the default is needed. Its return value will be returned.



## Callbacks

Several callback options are available for you to trigger code at different points in the retry lifecycle.

Backoff passes an `AttemptLog` object (or an array of them, depending on the callback) to these callbacks. See below for information about the [AttemptLog class](#logging).

> ***Note:*** These methods only apply when you use the [`->attempt()` method to manage the retry process](#use-backoff-to-manage-the-retry-process).



### Exception Callback

If you'd like to run some code *each time* an exception occurs, you can pass a callback to `->exceptionCallback(…)`.

It doesn't matter whether the exception is caught using [->retryExceptions(…)](#retry-when-particular-exceptions-occur). These callbacks will be called regardless of a retry being made or not.

```php
$callback = fn(Throwable $e, AttemptLog $log, bool $willRetry): void => …; // do something here

Backoff::exponential(1)
    ->exceptionCallback($callback) // <<<
    ->attempt($action);
```



### Invalid Result Callback

If you'd like to run some code *each time* an invalid result is returned, you can pass a callback to `->invalidResultCallback(…)`.

```php
$callback = fn(mixed $result, AttemptLog $log): void => …; // do something here

Backoff::exponential(1)
    ->invalidResultCallback($callback) // <<<
    ->attempt($action);
```



### Success Callback

You can specify a callback to be called once, after the attempt succeeds by calling `->successCallback(…)`.

An array of `AttemptLog` objects representing the attempts that were made will be passed to your callback.

```php
/** @var AttemptLog[] $logs */
$callback = fn(array $logs): void => …; // do something here

Backoff::exponential(1)
    ->successCallback($callback) // <<<
    ->attempt($action);
```



### Failure Callback

You can specify a callback to be called once, after all attempts have failed by calling `->failureCallback(…)`.

This includes if zero attempts were made, and when an exception is eventually thrown.

An array of `AttemptLog` objects representing the attempts that were made will be passed to your callback.

```php
/** @var AttemptLog[] $logs */
$callback = fn(array $logs): void => …; // do something here

Backoff::exponential(1)
    ->failureCallback($callback) // <<<
    ->attempt($action);
```



### Finally Callback

If you would like to run some code once, afterwards, regardless of the outcome, you can pass a callback to `->finallyCallback(…)`.

This includes if zero attempts were made, and when an exception is eventually thrown.

An array of `AttemptLog` objects representing the attempts that were made will be passed to your callback.

```php
/** @var AttemptLog[] $logs */
$callback = fn(array $logs): void => …; // do something here

Backoff::exponential(1)
    ->finallyCallback($callback) // <<<
    ->attempt($action);
```



## Logging

Backoff collects some basic information about each attempt as they happen.

This history is made up of [`AttemptLog` objects](#the-attemptlog-class), which you can use if you'd like to perform logging or analysis yourself.

When using `->attempt()`, you can access them via [callbacks](#callbacks).

If you're [managing the loop yourself](#or-manage-the-retry-loop-yourself), add `->startOfAttempt()` and `->endOfAttempt()` around your work so the logs are built. You can then access:
- the current `AttemptLog` by calling `$backoff->currentLog()`,
- and the full history (so far) using `$backoff->logs()`.

```php
$backoff = Backoff::exponential(1);
do {
    $backoff->startOfAttempt(); // <<<
    $success = …; // do some work
    $backoff->endOfAttempt(); // <<<
    
    $log = $backoff->currentLog(); // returns the current AttemptLog
    // … perform some logging here based upon $log
    
} while ((!$success) && ($backoff->step()));

$logs = $backoff->logs(); // returns all the AttemptLogs in an array
```



### The AttemptLog Class

The `AttemptLog` class contains basic information about each attempt that has happened.

They can be accessed via [callbacks](#callbacks), and when [managing the retry loop yourself](#logging).

`AttemptLog` contains the following methods:

```php
$log->attemptNumber(); // the attempt being made (1, 2, 3…)
$log->retryNumber();   // the retry being made (0, 1, 2…)

// the maximum possible attempts
// (returns null for unlimited attempts)
// note: it's possible for a backoff algorithm to return null
// so the attempts finish early. This won't be reflected here
$log->maxAttempts();   

$log->firstAttemptOccurredAt(); // when the first attempt started
$log->thisAttemptOccurredAt();  // when the current attempt started

// the time spent on this attempt
// (will be null until known)
$log->workingTime();          // in the current unit-of-measure
$log->workingTimeInSeconds(); // in seconds
$log->workingTimeInMs();      // in milliseconds
$log->workingTimeInUs();      // in microseconds

// the overall time spent attempting the action (so far)
// (sum of all working time, will be null until known)
$log->overallWorkingTime();          // in the current unit-of-measure
$log->overallWorkingTimeInSeconds(); // in seconds
$log->overallWorkingTimeInMs();      // in milliseconds
$log->overallWorkingTimeInUs();      // in microseconds

// the delay that was applied before this attempt
// (will be null for the first attempt)
$log->prevDelay();          // in the current unit-of-measure
$log->prevDelayInSeconds(); // in seconds
$log->prevDelayInMs();      // in milliseconds
$log->prevDelayInUs();      // in microseconds

// the delay that will be used before the next attempt
// (will be null if there are no more attempts left)
$log->nextDelay();          // in the current unit-of-measure
$log->nextDelayInSeconds(); // in seconds
$log->nextDelayInMs();      // in milliseconds
$log->nextDelayInUs();      // in microseconds

// the overall delay so far (sum of all delays)
$log->overallDelay();          // in the current unit-of-measure
$log->overallDelayInSeconds(); // in seconds
$log->overallDelayInMs();      // in milliseconds
$log->overallDelayInUs();      // in microseconds

// the unit-of-measure used
// these are values from CodeDistortion\Backoff\Settings::UNIT_XXX
$log->unitType();
```



## Backoff and Test Suites

When running your test-suite, you might want to disable the backoff delays, or stop retries altogether.



### Disabling Backoff

You can remove the delay between attempts using `->onlyDelayWhen(false)`.

The action may still be retried, but there won't be any delays between attempts.

```php
$runningTests = …;

Backoff::exponential(1)
    ->maxAttempts(10)
    // 0, 0, 0, 0, 0… delays when running tests
    ->onlyDelayWhen(!$runningTests) // <<<
    ->attempt($action);
```

When `$runningTests` is `true`, this is:
- equivalent to setting `->maxDelay(0)`, and
- is largely equivalent to using the `Backoff::noop()` backoff 4.



### Disabling Retries

Alternatively, you can disable retries altogether using `->onlyRetryWhen(false)`.

```php
$runningTests = …;

$backoff = Backoff::exponential(1)
    ->maxAttempts(10)
    // no reties when running tests
    ->onlyRetryWhen(!$runningTests) // <<<
    ->attempt($action);
```

When `$runningTests` is `true`, this is equivalent to:
- setting `->maxAttempts(1)`, or
- using the `Backoff::none()` backoff algorithm.



## Modelling

If you would like to model the backoff process, you can use a `Backoff` instance to generate sets of delays without actually sleeping.

```php
// generate delays in the current unit-of-measure
$backoff->simulate(1);      // generate a single delay (e.g. for retry 1)
$backoff->simulate(10, 20); // generate a sequence of delays, returned as an array (e.g. for retries 10 - 20)
```

Equivalent methods exist to retrieve the delays in seconds, milliseconds and microseconds.

```php
// generate delays in seconds (note: may contain decimals)
$backoff->simulateInSeconds(1);
$backoff->simulateInSeconds(1, 20);

// generate delays in milliseconds (note: may contain decimals)
$backoff->simulateInMs(1);
$backoff->simulateInMs(1, 20);

// generate delays in microseconds (note: may contain decimals)
$backoff->simulateInUs(1);
$backoff->simulateInUs(1, 20);
```

And just in case you need to check, you can retrieve the unit-of-measure being used.

```php
// these are values from CodeDistortion\Backoff\Settings::UNIT_XXX
$backoff->getUnitType();
```

`null` values in the results indicate that the attempts have been exhausted.

> ***Note:*** These methods will generate the same values when you call them again. Backoff maintains this state because some backoff algorithms base their delays on previously generated delays (e.g. the [decorrelated backoff algorithm](#decorrelated-backoff) does this), so their values are important.
> 
> e.g. When generating `$backoff->simulateDelays(1, 20);` and then `$backoff->simulateDelays(21, 40);`, the second set may be based on the first set. 
> 
> To generate a *new* set of delays, call `$backoff->reset()` first.
> 
> ```php
> $first = $backoff->simulateDelays(1, 20);
> $second = $backoff->simulateDelays(1, 20);
> // $second will be the same as $first
> $third = $backoff->reset()->simulateDelays(1, 20);
> // however $third will be different
> ```

> ***Info:*** If these methods don't work fast enough for you, you could look into the `DelayCalculator` class, which `Backoff` uses behind the scenes to calculate the delays.
>
> Generate delays with it, and then call `$delayCalculator->reset()` before generating a new set.



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
