<?php
namespace Corma\DataObject;


class DataObjectEvent implements DataObjectEventInterface
{
    public function __construct(protected object $object)
    {
    }

    public function getObject(): object
    {
        return $this->object;
    }
}
