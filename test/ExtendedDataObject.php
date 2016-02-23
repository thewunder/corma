<?php
namespace Corma\Test;

use Corma\DataObject\DataObject;

/**
 * A Fixture
 */
class ExtendedDataObject extends DataObject
{
    protected $myColumn;

    /**
     * @return mixed
     */
    public function getMyColumn()
    {
        return $this->myColumn;
    }

    /**
     * @param mixed $myColumn
     */
    public function setMyColumn($myColumn)
    {
        $this->myColumn = $myColumn;
    }
}