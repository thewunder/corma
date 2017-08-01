<?php
namespace Corma\Test\Fixtures;

class BaseDataObject
{
    protected $id;

    /** @var bool */
    protected $isDeleted;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDeleted()
    {
        return (bool) $this->isDeleted;
    }

    /**
     * @param boolean $isDeleted
     */
    public function setDeleted($isDeleted)
    {
        $this->isDeleted = $isDeleted;
    }
}
