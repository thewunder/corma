<?php
namespace Corma\DataObject\Factory;

use Corma\DataObject\Hydrator\ObjectHydratorInterface;

abstract class BaseObjectFactory implements ObjectFactoryInterface
{
    protected array $reflectionClasses = [];

    public function __construct(protected ObjectHydratorInterface $hydrator)
    {
    }

    public function create(string $class, array $data = [], array $dependencies = []): object
    {
        if (empty($dependencies)) {
            $object = new $class;
        } else {
            $reflectionClass = $this->getReflectionClass($class);
            $object = $reflectionClass->newInstanceArgs($dependencies);
        }

        if (!empty($data)) {
            $this->hydrator->hydrate($object, $data);
        }
        return $object;
    }

    protected function getReflectionClass(string $class): \ReflectionClass
    {
        if (isset($this->reflectionClasses[$class])) {
            return $this->reflectionClasses[$class];
        }
        return $this->reflectionClasses[$class] = new \ReflectionClass($class);
    }
}
