<?php
namespace Corma\DataObject;
use Doctrine\DBAL\Driver\Statement;


/**
 * Manages the construction of objects
 */
interface ObjectFactoryInterface
{
    /**
     * @param string $class
     * @param array $dependencies
     * @param array $data
     * @return object
     */
    public function create($class, array $dependencies, array $data = []);

    /**
     * Retrieves all items from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param Statement|\PDOStatement $statement
     * @param array $dependencies
     * @return \object[]
     */
    public function fetchAll($class, $statement, array $dependencies);

    /**
     * Retrieves a single item from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param Statement $statement
     * @param array $dependencies
     * @return object
     */
    public function fetchOne($class, $statement, array $dependencies);
}