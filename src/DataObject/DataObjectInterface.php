<?php
namespace Corma\DataObject;


/**
 * An object that can be persisted and retrieved by a ObjectMapper ObjectRepository
 */
interface DataObjectInterface
{
    /**
     * Get the table this data object is persisted in
     *
     * @return string
     */
    public static function getTableName();

    /**
     * Get class minus namespace
     *
     * @return string
     */
    public static function getClassName();

    /**
     * @param DataObjectInterface[] $objects
     * @return array
     */
    public static function getIds(array $objects);

    /**
     * @return string
     */
    public function getId();

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id);

    /**
     * @param bool $isDeleted
     * @return $this
     */
    public function setIsDeleted($isDeleted);

    /**
     * @return bool
     */
    public function getIsDeleted();

    /**
     * Sets the data provided to the properties of the object
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data);
}