<?php
namespace Corma\DataObject\Factory;

use Doctrine\DBAL\Driver\ResultStatement;

/**
 * Manages the construction of objects
 */
interface ObjectFactoryInterface
{
    /**
     * Retrieves a single instance of the specified object
     *
     * @param string $class
     * @param array $dependencies
     * @param array $data
     * @return object
     */
    public function create(string $class, array $dependencies = [], array $data = []): object;

    /**
     * Retrieves all items from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param ResultStatement|\PDOStatement $statement
     * @param array $dependencies
     * @return object[]
     */
    public function fetchAll(string $class, $statement, array $dependencies = []): array;

    /**
     * Retrieves a single item from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param ResultStatement $statement
     * @param array $dependencies
     * @return object|null
     */
    public function fetchOne(string $class, $statement, array $dependencies = []): ?object;
}
