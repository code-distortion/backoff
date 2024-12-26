<?php

declare(strict_types=1);

namespace CodeDistortion\Backoff\Tests\Unit\Support;

use ReflectionClass;
use ReflectionException;

/**
 * A class to help with testing.
 */
class TestSupport
{
    /**
     * Determine if the current OS is Windows or Mac.
     *
     * @return boolean
     */
    public static function isWindowsOrMac(): bool
    {
        return in_array(PHP_OS_FAMILY, ['Windows', 'Darwin'], true); // Darwin = Mac
    }



    /**
     * Call a private method on an object.
     *
     * @param object               $instance The object to call the method on.
     * @param string               $method   The name of the method to call.
     * @param array<integer,mixed> $args     The arguments to pass to the method.
     * @return mixed
     * @throws ReflectionException Thrown if the method does not exist.
     */
    public static function callPrivateMethod(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($instance);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invoke($instance, ...$args);
    }

    /**
     * Get the value of a private property on an object.
     *
     * @param object $instance The object to get the property from.
     * @param string $property The name of the property to get.
     * @return mixed
     * @throws ReflectionException Thrown if the property does not exist.
     */
    public static function getPrivateProperty(object $instance, string $property): mixed
    {
        $reflection = new ReflectionClass($instance);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($instance);
    }

    /**
     * Set the value of a private property on an object.
     *
     * @param object $instance The object to set the property on.
     * @param string $property The name of the property to set.
     * @param mixed  $value    The value to set the property to.
     * @return void
     * @throws ReflectionException Thrown if the property does not exist.
     */
    public static function setPrivateProperty(object $instance, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($instance);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        $property->setValue($instance, $value);
    }

    /**
     * Get the value of a private property on an object.
     *
     * @param class-string $class    The class to get the property from.
     * @param string       $property The name of the property to get.
     * @return mixed
     * @throws ReflectionException Thrown if the property does not exist.
     */
    public static function getPrivateStaticProperty(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue();
    }

    /**
     * Set the value of a private property on an object.
     *
     * @param class-string $class    The class to set the property on.
     * @param string       $property The name of the property to set.
     * @param mixed        $value    The value to set the property to.
     * @return void
     * @throws ReflectionException Thrown if the property does not exist.
     */
    public static function setPrivateStaticProperty(string $class, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        $property->setValue($reflection, $value);
    }
}
