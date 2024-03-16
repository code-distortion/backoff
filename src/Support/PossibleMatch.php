<?php

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
        public mixed $value,
        public bool $hasDefault = false,
        public mixed $default = null,
        public bool $strict = false,
    ) {
    }


//    /**
//     * Check if a result matches the settings stored in this object.
//     *
//     * @param mixed $result The result to check.
//     * @return boolean
//     */
//    public function matches(mixed $result): bool
//    {
//        if (is_callable($this->value)) {
//
//            $callback = $this->value;
//            if ($callback($result)) {
//                return true;
//            }
//
//        } else {
//
//            if (($this->strict) && ($result === $this->value)) {
//                return true;
//            }
//
//            if ((!$this->strict) && ($result == $this->value)) {
//                return true;
//            }
//        }
//
//        return false;
//    }



    /**
     * Find out if a default value was specified.
     *
     * @return boolean
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Get the default value.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }
}
