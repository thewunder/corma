<?php
namespace Corma\DataObject\TableConvention;

/**
 * Infers the database table name from the class or object passed in
 */
interface TableConventionInterface
{
    /**
     * Gets the name of the database table for the supplied object
     *
     * @param object|string $classOrObject
     * @return string
     */
    public function getTable(object|string $classOrObject): string;
}
