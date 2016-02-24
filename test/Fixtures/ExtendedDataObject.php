<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\DataObject;

/**
 * A Fixture
 */
class ExtendedDataObject extends DataObject
{
    protected $myColumn, $myNullableColumn;

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
}