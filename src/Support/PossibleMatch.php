<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Support;

/**
 * A class to contain details about results that should (or shouldn't) be matched against, or exceptions that should be
 * caught.
 */
class PossibleMatch
{
    /**
     * Constructor.
     *
     * @param mixed   $value      The value to match (or not match).
     * @param boolean $hasDefault Whether a default value has been provided.
     * @param mixed   $default    The default value to use.
     * @param boolean $strict     Whether to use strict comparison.
     */
    public function __construct(
        public mixed $value = null,
        public bool $hasDefault = false,
        public mixed $default = null,
        public bool $strict = false,
    ) {
    }
}
