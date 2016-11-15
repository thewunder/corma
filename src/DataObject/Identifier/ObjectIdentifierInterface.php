<?php
namespace Corma\DataObject\Identifier;

/**
 * Manages getting, setting, and generating id's for objects
 */
interface ObjectIdentifierInterface
{
    /**
     * @param object $object
     * @return string
     */
    public function getId($object);

    /**
     * @param object[] $objects
     * @return string[]
     */
    public function getIds(array $objects);

    /**
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
    public function getIdColumn($objectOrClass);
}