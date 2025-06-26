<?php
namespace Corma\DataObject\Hydrator;

interface PropertyHydrator
{
    /**
     * Returns the types of properties this hydrator supports.
     *
     * @return string[]
     */
    public function getSupportedTypes(): array;

    /**
     * @param mixed $dbValue The value as stored in the database
     * @return mixed The object or other value transformed
     */
    public function hydratedValue(mixed $dbValue): mixed;

    /**
     * @param mixed $hydratedValue The value from the object property
     * @return mixed The value to store in the database
     */
    public function databaseValue(mixed $hydratedValue): mixed;
}
