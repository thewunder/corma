<?php
namespace Corma\DataObject\Factory;

use Corma\DataObject\Hydrator\ObjectHydratorInterface;

abstract class BaseObjectFactory implements ObjectFactoryInterface
{
    protected $reflectionClasses = [];

    /**
     * @var ObjectHydratorInterface
     */
    protected $hydrator;


    public function __construct(ObjectHydratorInterface $hydrator)
    {
        $this->hydrator = $hydrator;
    }

    public function create(string $class, array $dependencies = [], array $data = []): object
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

    protected function getReflectionClass(string $class)
    {
        if (isset($this->reflectionClasses[$class])) {
            return $this->reflectionClasses[$class];
        }
        return $this->reflectionClasses[$class] = new \ReflectionClass($class);
    }
}
