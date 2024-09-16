<?php
namespace Corma\DataObject\Factory;

use Corma\DBAL\Result;

/**
 * Manages the construction of objects
 */
interface ObjectFactoryInterface
{
    /**
     * Retrieves a single instance of the specified object
     *
     * @param string $class Fully qualified class name of the object to create
     * @param array $data Data to hydrate the object with
     * @param array $dependencies Dependencies to construct the object with
     * @return object
     */
    public function create(string $class, array $data = [], array $dependencies = []): object;

    /**
     * Retrieves all items from select statement, hydrated, and with dependencies
     *
     * @param string $class Fully qualified class name of the object to create
     * @param Result $statement Database result statement
     * @param array $dependencies Dependencies to construct the object with
     * @return object[]
     */
    public function fetchAll(string $class, Result $statement, array $dependencies = []): array;

    /**
     * Retrieves a single item from select statement, hydrated, and with dependencies
     *
     * @param string $class Fully qualified class name of the object to create
     * @param Result $statement Database result statement
     * @param array $dependencies Dependencies to construct the object with
     * @return object|null
     */
    public function fetchOne(string $class, Result $statement, array $dependencies = []): ?object;
}
