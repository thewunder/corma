<?php
namespace Corma\Util;

use Doctrine\Common\Inflector\Inflector as DoctrineInflector;

/**
 * Utility class to get method names from column names and class names and vice versa
 */
class Inflector
{
    /**
     * Gets the class minus namespace
     *
     * @param $classOrObject
     * @return string
     */
    public function getShortClass($classOrObject)
    {
        if(is_string($classOrObject)) {
            $class = $classOrObject;
        } else {
            $class = get_class($classOrObject);
        }

        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * @param string $columnName
     * @param bool $plural
     * @return string Partial method name to get / set object(s)
     */
    public function methodNameFromColumn(string $columnName, bool $plural = false)
    {
        $method = ucfirst(str_replace(['Id', '_id'], '', $columnName));
        if ($plural) {
            return DoctrineInflector::pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @param string $className
     * @param bool $plural
     * @return string Partial method name to get / set object(s)
     */
    public function methodNameFromClass(string $className, bool $plural = false)
    {
        $method = substr($className, strrpos($className, '\\') + 1);
        if ($plural) {
            return DoctrineInflector::pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @param string $column
     * @return string
     */
    public function getterFromColumn(string $column)
    {
        return 'get' . DoctrineInflector::classify($column);
    }

    /**
     * @param string $column
     * @return string
     */
    public function setterFromColumn(string $column)
    {
        return 'set' . DoctrineInflector::classify($column);
    }

    /**
     * @param string $className With or without namespace
     * @param string $suffix
     * @return string
     */
    public function idColumnFromClass(string $className, ?string $suffix = 'Id')
    {
        return lcfirst(substr($className, strrpos($className, '\\') + 1)) . $suffix;
    }
}
