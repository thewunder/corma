<?php
namespace Corma\Util;

use Doctrine\Common\Inflector\Inflector as DoctrineInflector;

/**
 * Utility class to get method names from column names and class names and vice versa
 */
class Inflector
{
    /**
     * @param string $columnName
     * @param bool $plural
     * @return string Partial method name to get / set object(s)
     */
    public function methodNameFromColumn($columnName, $plural = false)
    {
        $method = ucfirst(str_replace(['Id', '_id'], '', $columnName));
        if($plural) {
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
    public function methodNameFromClass($className, $plural = false)
    {
        $method = substr($className, strrpos($className, '\\') + 1);
        if($plural) {
            return DoctrineInflector::pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @param string $className With or without namespace
     * @param string $suffix
     * @return string
     */
    public function idColumnFromClass($className, $suffix = 'Id')
    {
        return lcfirst(substr($className, strrpos($className, '\\') + 1)) . $suffix;
    }
}
