<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;

/**
 * A Fixture
 */
class ExtendedDataObject extends DataObject
{
    protected $myColumn, $myNullableColumn;

    /** @var array */
    protected $arrayProperty;

    /** @var DataObjectInterface */
    protected $objectProperty;

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
     * @return DataObjectInterface
     */
    public function getObjectProperty()
    {
        return $this->objectProperty;
    }

    /**
     * @param DataObjectInterface $objectProperty
     * @return ExtendedDataObject
     */
    public function setObjectProperty($objectProperty)
    {
        $this->objectProperty = $objectProperty;
        return $this;
    }
}