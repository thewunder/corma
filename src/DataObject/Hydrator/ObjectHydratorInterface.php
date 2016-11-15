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
     * @param object $object
     * @param array $data
     * @return object
     */
    public function hydrate($object, array $data);

    /**
     * Extracts all scalar data from the object
     *
     * @param object $object
     * @return array
     */
    public function extract($object);
}