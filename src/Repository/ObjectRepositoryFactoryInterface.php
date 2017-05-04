<?php
namespace Corma\Repository;

interface ObjectRepositoryFactoryInterface
{
    /**
     * @param string $objectName The fully qualified object class name
     * @return ObjectRepositoryInterface
     */
    public function getRepository(string $objectName): ?ObjectRepositoryInterface;
}
