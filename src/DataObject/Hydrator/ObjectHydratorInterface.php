<?php
namespace Corma\DataObject\Hydrator;

/**
 * Manages setting data onto an object, and retrieving data from an object
 */
interface ObjectHydratorInterface
{
    /**
     * Sets the supplied data on to the object.
     *
     * @return object
     */
    public function hydrate(object $object, array $data);

    /**
     * Extracts all scalar data from the object
     *
     * @return array
     */
    public function extract(object $object): array;
}
