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
     * @return array The objects.
     */
    public function findAll();

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
     * @return array The objects.
     *
     * @throws \UnexpectedValueException
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null);

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria column => value pairs to be used to build where clause
     * @param array $orderBy column => ASC / DESC pairs
     *
     * @return object|null The object.
     */
    public function findOneBy(array $criteria, array $orderBy = []);

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return string
     */
    public function getClassName();

    /**
     * Creates a new instance of the object
     *
     * @param array $data Optional array of data to set on object after instantiation
     * @return object
     */
    public function create(array $data = []);

    /**
     * @param mixed $id The Identifier
     * @param bool $useCache Use cache?
     * @return mixed
     */
    public function find($id, bool $useCache = true);

    /**
     * Find one or more data objects by id
     *
     * @param array $ids
     * @param bool $useCache Use cache?
     * @return object[]
     */
    public function findByIds(array $ids, bool $useCache = true): array;

    /**
     * Return the database table for an object
     *
     * @param mixed $objectOrClass If omitted will return table for the object this repository manages
     * @return string The name of the database table
     */
    public function getTableName($objectOrClass = null): string;

    /**
     * Persists the object to the database
     *
     * @param object $object
     * @return object
     */
    public function save($object);

    /**
     * Persists all supplied objects into the database
     **
     * @param object[] $objects
     * @return int The number of effected rows
     */
    public function saveAll(array $objects);

    /**
     * Removes the object from the database
     *
     * @param object $object
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function delete($object);

    /**
     * Deletes all objects by id
     *
     * @param object[] $objects
     */
    public function deleteAll(array $objects);

    /**
     * Retrieves the object manager for the class managed by this repository.
     *
     * If you need to customize the hydration, id behavior, table name, or object instantiation behavior, you will override this method.
     *
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager;
}
