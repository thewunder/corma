<?php
namespace Corma\Repository;

interface ObjectRepositoryFactoryInterface
{
    /**
     * @param string $objectName
     * @return ObjectRepositoryInterface
     */
    public function getRepository($objectName);
}