<?php
namespace Corma\Repository;

interface ObjectRepositoryFactoryInterface
{
    /**
     * @param string $objectName The object class with or without namespace
     * @return ObjectRepositoryInterface
     */
    public function getRepository(string $objectName): ?ObjectRepositoryInterface;
}
