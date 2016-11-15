<?php


namespace Corma\DataObject;


interface ObjectHydratorInterface
{
    public function hydrate($object, array $data);

    public function extract($object);
}