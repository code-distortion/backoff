<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff;

/**
 * General Settings used by the backoff classes.
 */
abstract class Settings
{
    /** @var string The available units of time measurement. */
    public const UNIT_SECONDS = 'seconds';
    public const UNIT_MILLISECONDS = 'milliseconds';
    public const UNIT_MICROSECONDS = 'microseconds';

    /** @var string[] A list of the possible unit types. */
    public const ALL_UNIT_TYPES = [
        self::UNIT_SECONDS,
        self::UNIT_MILLISECONDS,
        self::UNIT_MICROSECONDS,
    ];
}
