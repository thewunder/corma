<?php


namespace Corma\DataObject\Factory;


use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\FetchMode;
use Psr\Container\ContainerInterface;

/**
 * Factory that delegates object construction to a PSR-11 compatible dependency injection container.
 * When get() is called on the container with the full class name it must return a new instance of the requested class.
 * If the container does not have the requested class it will fall back to instantiation with reflection
 *
 * Passing dependencies to any of the methods will bypass the container and directly instantiate the class via reflection.
 */
class PsrContainerObjectFactory extends BaseObjectFactory implements ObjectFactoryInterface
{
    public function __construct(private readonly ContainerInterface $container, ObjectHydratorInterface $hydrator)
    {
        parent::__construct($hydrator);
    }

    public function create(string $class, array $data = [], array $dependencies = []): object
    {
        if (!$this->container->has($class)) {
            return parent::create($class, $data, $dependencies);
        }

        $instance = $this->container->get($class);
        if (!empty($data)) {
            $this->hydrator->hydrate($instance, $data);
        }
        return $instance;
    }

    public function fetchAll(string $class, Result $statement, array $dependencies = []): array
    {
        $results = [];
        while ($data = $statement->fetchAssociative()) {
            $object = $this->create($class, $data, $dependencies);
            $results[] = $object;
        }

        return $results;
    }

    public function fetchOne(string $class, Result $statement, array $dependencies = []): ?object
    {
        $data = $statement->fetchAssociative();
        if (!empty($data)) {
            return $this->create($class, $data, $dependencies);
        }
        return null;
    }
}
