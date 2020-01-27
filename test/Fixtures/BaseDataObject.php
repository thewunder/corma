<?php
namespace Corma\Test\Fixtures;

class BaseDataObject
{
    protected $id;

    /** @var bool */
    protected $isDeleted = false;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function isDeleted()
    {
        return (bool) $this->isDeleted;
    }

    public function setDeleted(bool $isDeleted)
    {
        $this->isDeleted = $isDeleted;
    }
}
