<?php
namespace Corma\DataObject;

use Symfony\Component\EventDispatcher\Event as BaseEvent;

class DataObjectEvent extends BaseEvent implements DataObjectEventInterface
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
