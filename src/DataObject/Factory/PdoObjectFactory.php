<?php
namespace Corma\DataObject\Factory;

/**
 * Factory that when retrieving objects from the database uses PDO both to hydrate and set dependencies.
 * PDO sets properties directly (bypassing accessor methods) and calls the constructor after hydration.
 */
class PdoObjectFactory extends BaseObjectFactory
{
    public function fetchAll($class, $statement, array $dependencies = [])
    {
        return $statement->fetchAll(\PDO::FETCH_CLASS, $class, $dependencies);
    }

    public function fetchOne($class, $statement, array $dependencies = [])
    {
        $statement->setFetchMode(\PDO::FETCH_CLASS, $class, $dependencies);
        return $statement->fetch();
    }
}