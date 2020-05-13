<?php
namespace Corma\DataObject\Factory;

/**
 * Factory that when retrieving objects from the database uses PDO both to hydrate and set dependencies.
 * PDO sets properties directly (bypassing accessor methods) and calls the constructor after hydration.
 */
class PdoObjectFactory extends BaseObjectFactory
{
    public function fetchAll(string $class, $statement, array $dependencies = []): array
    {
        return $statement->fetchAll(\PDO::FETCH_CLASS, $class, $dependencies);
    }

    public function fetchOne(string $class, $statement, array $dependencies = []): ?object
    {
        $statement->setFetchMode(\PDO::FETCH_CLASS, $class, $dependencies);
        $result = $statement->fetch();
        return is_object($result) ? $result : null;
    }
}
