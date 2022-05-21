<?php
namespace Corma\DataObject;

use Symfony\Contracts\EventDispatcher\Event;

class DataObjectEvent extends Event implements DataObjectEventInterface
{
    public function __construct(protected object $object)
    {
    }

    public function getObject(): object
    {
        return $this->object;
    }
}
