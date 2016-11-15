<?php


namespace Corma\DataObject\Hydrator;


interface ObjectHydratorInterface
{
    public function hydrate($object, array $data);

    public function extract($object);
}