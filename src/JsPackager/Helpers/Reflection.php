<?php
/**
 * @category WebPT
 * @copyright Copyright (c) 2012 WebPT, INC
 * @author jwilson
 * 11/20/12 9:29 AM
 */
namespace JsPackager\Helpers;

use ReflectionClass;

class Reflection
{
    /**
     * Get a property that may be private or protected.
     * @static
     * @param mixed $object
     * @param string $property
     * @return mixed
     */
    public static function get($object, $property)
    {
        $class = new ReflectionClass($object);

        $classProp = $class->getProperty($property);
        $classProp->setAccessible(true);

        if(is_object($object)) {
            return $classProp->getValue($object);
        } else {
            return $classProp->getValue();
        }
    }

    /**
     * Invoke a class method that may be private or protected.
     * @static
     * @param mixed $object
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function invoke($object, $method, $args = array())
    {
        $class = new ReflectionClass($object);

        $classMethod = $class->getMethod($method);
        $classMethod->setAccessible(true);

        if(is_object($object)) {
            return $classMethod->invokeArgs($object, $args);
        } else {
            return $classMethod->invokeArgs(null, $args);
        }
    }

    /**
     * Set a property that may be private or protected.
     * @static
     * @param mixed $object
     * @param string $property
     * @param mixed $value
     * @return bool
     */
    public static function set($object, $property, $value)
    {
        $class = new ReflectionClass($object);

        if ($class->hasProperty($property) == true) {
            $classProp = $class->getProperty($property);
            $classProp->setAccessible(true);
            $classProp->setValue($object, $value);
            return true;
        }

        return false;
    }

    /**
     * Set a static property that may be private or protected.
     * @static
     * @param mixed $object
     * @param string $property
     * @param mixed $value
     * @return bool
     */
    public static function setStatic($object, $property, $value)
    {
        $class = new ReflectionClass($object);

        if ($class->hasProperty($property) == true) {
            $classProp = $class->getProperty($property);
            $classProp->setAccessible(true);
            $classProp->setValue($value);
            return true;
        }

        return false;
    }
}
