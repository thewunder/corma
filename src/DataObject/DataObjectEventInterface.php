<?php
namespace Corma\DataObject;

interface DataObjectEventInterface
{
    /**
     * @return DataObjectInterface
     */
    public function getObject();
}
