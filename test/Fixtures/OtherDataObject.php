<?php
namespace Corma\Test\Fixtures;

use Corma\DataObject\DataObject;

class OtherDataObject extends DataObject
{
    protected $name;

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
}