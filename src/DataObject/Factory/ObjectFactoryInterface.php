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
     * @param array $data
     * @param array $dependencies
     * @return object
     */
    public function create(string $class, array $data = [], array $dependencies = []): object;

    /**
     * Retrieves all items from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param ResultStatement $statement
     * @param array $dependencies
     * @return object[]
     */
    public function fetchAll(string $class, ResultStatement $statement, array $dependencies = []): array;

    /**
     * Retrieves a single item from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param ResultStatement $statement
     * @param array $dependencies
     * @return object|null
     */
    public function fetchOne(string $class, ResultStatement $statement, array $dependencies = []): ?object;
}
