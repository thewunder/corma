<?php
namespace Corma\Repository;

use Corma\DataObject\ObjectManager;

/**
 * Interface for object repositories.
 * An object repository manages all creation, persistence, retrieval, deletion of objects of a particular class.
 */
interface ObjectRepositoryInterface
{
    /**
     * Finds all objects in the repository.
     *
     * @return object[] The objects.
     */
    public function findAll(): array;

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array      $criteria column => value pairs to be used to build where clause
     * @param array      $orderBy column => ASC / DESC pairs
     * @param int|null   $limit
     * @param int|null   $offset
     *
     * @return object[] The objects.
     *
     * @throws \UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria column => value pairs to be used to build where clause
     * @param array $orderBy column => ASC / DESC pairs
     *
     * @return object|null The object.
     */
    public function findOneBy(array $criteria, array $orderBy = []): ?object;

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName(): string;

    /**
     * Creates a new instance of the object
     *
     * @param array $data Optional array of data to set on object after instantiation
     * @return object
     */
    public function create(array $data = []): object;

    /**
     * @param string|int $id The Identifier
     * @param bool $useCache Use cache?
     * @return object|null
     */
    public function find(string|int $id, bool $useCache = true): ?object;

    /**
     * Find one or more data objects by id
     *
     * @param bool $useCache Use cache?
     * @return object[]
     */
    public function findByIds(array $ids, bool $useCache = true): array;

    /**
     * Return the database table for an object
     *
     * @param object|string|null $objectOrClass If omitted will return table for the object this repository manages
     * @return string The name of the database table
     */
    public function getTableName(object|string $objectOrClass = null): string;

    /**
     * Persists the object to the database
     *
     * @param object $object The object to save
     * @param \Closure|null $saveRelationships If the repository provides a saveRelationships closure then omitting
     * will use the default specified by the repository. Explicitly passing null will not save any relationships even
     * if the repository returns a closure from saveRelationships.
     *
     * @return object The saved object
     */
    public function save(object $object, ?\Closure $saveRelationships = null): object;

    /**
     * Persists all supplied objects into the database
     *
     * @param object[] $objects The objects to save
     * @param \Closure|null $saveRelationships If the repository provides a saveRelationships closure then omitting
     * will use the default specified by the repository. Explicitly passing null will not save any relationships even
     * if the repository returns a closure from saveRelationships.
     *
     * @return int The number of effected rows
     */
    public function saveAll(array $objects, ?\Closure $saveRelationships = null): int;

    /**
     * Removes the object from the database
     */
    public function delete(object $object): void;

    /**
     * Deletes all objects by id
     *
     * @param object[] $objects
     * @return int The number of objects deleted
     */
    public function deleteAll(array $objects): int;

    /**
     * Retrieves the object manager for the class managed by this repository.
     *
     * If you need to customize the hydration, id behavior, table name, or object instantiation behavior, you will override this method.
     *
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager;
}
