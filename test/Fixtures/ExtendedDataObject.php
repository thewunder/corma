<?php
namespace Corma\Test\Fixtures;

/**
 * A Fixture
 */
class ExtendedDataObject extends BaseDataObject
{
    protected $myColumn;
    protected $myNullableColumn;
    protected $otherDataObjectId;

    /** @var array */
    protected $arrayProperty;

    /** @var ExtendedDataObject */
    protected $objectProperty;

    /** @var OtherDataObject */
    protected $otherDataObject;

    /** @var OtherDataObject[] */
    protected $otherDataObjects;

    /** @var OtherDataObject[] */
    protected $custom;

    /**
     * @return mixed
     */
    public function getMyColumn()
    {
        return $this->myColumn;
    }

    /**
     * @param mixed $myColumn
     * @return $this
     */
    public function setMyColumn($myColumn)
    {
        $this->myColumn = $myColumn;
        return $this;
    }

    /**
     * @return int
     */
    public function getMyNullableColumn()
    {
        return $this->myNullableColumn;
    }

    /**
     * @param int $myNullableColumn
     * @return ExtendedDataObject
     */
    public function setMyNullableColumn($myNullableColumn)
    {
        $this->myNullableColumn = $myNullableColumn;
        return $this;
    }

    /**
     * @return array
     */
    public function getArrayProperty()
    {
        return $this->arrayProperty;
    }

    /**
     * @param array $arrayProperty
     * @return ExtendedDataObject
     */
    public function setArrayProperty($arrayProperty)
    {
        $this->arrayProperty = $arrayProperty;
        return $this;
    }

    /**
     * @return ExtendedDataObject
     */
    public function getObjectProperty()
    {
        return $this->objectProperty;
    }

    /**
     * @param ExtendedDataObject $objectProperty
     * @return ExtendedDataObject
     */
    public function setObjectProperty($objectProperty)
    {
        $this->objectProperty = $objectProperty;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOtherDataObjectId()
    {
        return $this->otherDataObjectId;
    }

    /**
     * @param mixed $otherDataObjectId
     * @return ExtendedDataObject
     */
    public function setOtherDataObjectId($otherDataObjectId)
    {
        $this->otherDataObjectId = $otherDataObjectId;
        return $this;
    }

    /**
     * @return OtherDataObject
     */
    public function getOtherDataObject()
    {
        return $this->otherDataObject;
    }

    /**
     * @param OtherDataObject $otherDataObject
     * @return $this
     */
    public function setOtherDataObject(OtherDataObject $otherDataObject)
    {
        $this->otherDataObject = $otherDataObject;
        return $this;
    }

    /**
     * @param OtherDataObject[] $otherDataObjects
     * @return ExtendedDataObject
     */
    public function setOtherDataObjects($otherDataObjects)
    {
        $this->otherDataObjects = $otherDataObjects;
        return $this;
    }

    /**
     * @return OtherDataObject[]
     */
    public function getOtherDataObjects()
    {
        return $this->otherDataObjects;
    }

    /**
     * @return OtherDataObject[]
     */
    public function getCustom(): array
    {
        return $this->custom;
    }

    /**
     * @param OtherDataObject[] $custom
     */
    public function setCustom(array $custom)
    {
        $this->custom = $custom;
    }
}
