<?php
namespace Corma\DataObject;

use Symfony\Component\EventDispatcher\Event as BaseEvent;

class Event extends BaseEvent
{
    /** @var DataObjectInterface */
    protected $object;

    public function __construct(DataObjectInterface $object)
    {
        $this->object = $object;
    }

    /**
     * @return DataObjectInterface
     */
    public function getObject()
    {
        return $this->object;
    }
}