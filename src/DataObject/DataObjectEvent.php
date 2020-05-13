<?php
namespace Corma\DataObject;

use Symfony\Contracts\EventDispatcher\Event;

class DataObjectEvent extends Event implements DataObjectEventInterface
{
    protected $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }
}
