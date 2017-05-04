<?php
namespace Corma\DataObject\Identifier;

/**
 * Manages getting, setting, and generating id's for objects
 */
interface ObjectIdentifierInterface
{
    /**
     * Gets the primary key for the object
     *
     * @param object $object
     * @return string
     */
    public function getId($object): ?string;

    /**
     * Returns true if this object has not yet been persisted into the database
     *
     * @param object $object
     * @return bool
     */
    public function isNew($object): bool;

    /**
     * Gets the primary key for the supplied objects
     *
     * @param object[] $objects
     * @return string[]
     */
    public function getIds(array $objects): array;

    /**
     * Sets the primary key on the object
     *
     * @param object $object
     * @param string $id
     * @return object The object passed in
     */
    public function setId($object, $id);

    /**
     * Generates a new id for the object and sets it
     *
     * @param object $object
     * @return object The object passed in
     */
    public function setNewId($object);

    /**
     * @param string|object $objectOrClass
     * @return string Database column name containing the identifier for the object
     */
    public function getIdColumn($objectOrClass): string;
}