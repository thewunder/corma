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
     *
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
     * Loads a foreign relationship where a property on the supplied objects references an id for another object.
     *
     * Can be used to load a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Column / property on objects that relates to the foreign table's id
     * @return object[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, string $className, ?string $foreignIdColumn = null): array;

    /**
     * Loads a foreign relationship where a column on another object references the id for the supplied objects.
     *
     * Used to load the "many" side of a one-to-many relationship.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Column / property on foreign object that relates to the objects id
     * @param string $setter Name of setter method on objects
     * @return array|\object[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, string $className, ?string $foreignColumn = null, ?string $setter = null): array;

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     * @return object[] Loaded objects keyed by id
     */
    public function loadManyToMany(array $objects, string $className, string $linkTable, ?string $idColumn = null, ?string $foreignIdColumn = null): array;

    /**
     * @return ObjectManager
     */
    public function getObjectManager(): ObjectManager ;
}
