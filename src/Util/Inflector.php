<?php
namespace Corma\Util;

use Corma\Util\Inflector\Rules;
use Doctrine\Inflector\CachedWordInflector;
use Doctrine\Inflector\Inflector as DoctrineInflector;
use Doctrine\Inflector\RulesetInflector;

/**
 * Utility class to get method names from column names and class names and vice versa
 */
class Inflector extends DoctrineInflector
{
    public static function build(): self
    {
        return new self(
            new CachedWordInflector(new RulesetInflector(Rules::getSingularRuleset())),
            new CachedWordInflector(new RulesetInflector(Rules::getPluralRuleset()))
        );
    }

    /**
     * Gets the class minus namespace
     *
     * @param $classOrObject
     * @return string
     */
    public function getShortClass($classOrObject): string
    {
        if (is_string($classOrObject)) {
            $class = $classOrObject;
        } else {
            $class = $classOrObject::class;
        }

        return substr((string) $class, strrpos((string) $class, '\\') + 1);
    }

    /**
     * @return string Partial method name to get / set object(s)
     */
    public function methodNameFromColumn(string $columnName, bool $plural = false): string
    {
        $method = ucfirst(str_replace(['Id', '_id'], '', $columnName));
        if ($plural) {
            return $this->pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @return string Partial method name to get / set object(s)
     */
    public function methodNameFromClass(string $className, bool $plural = false): string
    {
        $method = substr($className, strrpos($className, '\\') + 1);
        if ($plural) {
            return $this->pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @return string
     */
    public function getterFromColumn(string $column): string
    {
        return 'get' . $this->classify($column);
    }

    /**
     * @return string
     */
    public function setterFromColumn(string $column): string
    {
        return 'set' . $this->classify($column);
    }

    /**
     * @param string $className With or without namespace
     * @param string|null $suffix
     * @return string
     */
    public function idColumnFromClass(string $className, ?string $suffix = 'Id'): string
    {
        return lcfirst(substr($className, strrpos($className, '\\') + 1)) . $suffix;
    }

    public function idColumnFromProperty(string $propertyName, ?string $suffix = 'Id'): string
    {
        return $propertyName . $suffix;
    }

    /**
     * Converts a camel case property name to an abbreviation using the first letter of each word.
     *
     * Ex. camelCaseProperty becomes ccp
     *
     * @param string $propertyName
     * @return string Database alias
     */
    public function aliasFromProperty(string $propertyName): string
    {
        if (empty($propertyName)) {
            return '';
        }
        $capitals = preg_replace('/[^A-Z]/u', '', $propertyName);
        return $propertyName[0] . mb_strtolower($capitals);
    }
}
