<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\DataObject;

class OtherDataObject extends DataObject
{
    protected $name;
    protected $extendedDataObjectId;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     * @return OtherDataObject
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExtendedDataObjectId()
    {
        return $this->extendedDataObjectId;
    }

    /**
     * @param mixed $extendedDataObjectId
     * @return OtherDataObject
     */
    public function setExtendedDataObjectId($extendedDataObjectId)
    {
        $this->extendedDataObjectId = $extendedDataObjectId;
        return $this;
    }
}
