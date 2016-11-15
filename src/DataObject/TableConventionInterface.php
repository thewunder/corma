<?php
namespace Corma\DataObject;

/**
 * Infers the database table name from the class or object passed in
 */
interface TableConventionInterface
{
    /**
     * @param string|object $classOrObject
     * @return string
     */
    public function getTable($classOrObject);
}