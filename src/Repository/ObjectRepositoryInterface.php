<?php
namespace Corma\Repository;

use Corma\DataObject\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository as DoctrineObjectRepository;

/**
 * Interface for object repositories.
 * An object repository manages all creation, persistence, retrieval, deletion of objects of a particular class.
 */
interface ObjectRepositoryInterface extends DoctrineObjectRepository
{
    /**
     * Creates a new instance of the object
     *
     * @return object
     */
    public function create();

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
     * Return the database table this repository manages
     *
     * @return string
     */
    public function getTableName(): string;

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
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager;
}
