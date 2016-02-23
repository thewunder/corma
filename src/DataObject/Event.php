<?php
namespace Corma\DataObject;

use Symfony\Component\EventDispatcher\Event as BaseEvent;

class Event extends BaseEvent
{
    /** @var  DataObject */
    protected $object;

    public function __construct(DataObject $object)
    {
        $this->object = $object;
    }

    /**
     * @return DataObject
     */
    public function getObject()
    {
        return $this->object;
    }
}