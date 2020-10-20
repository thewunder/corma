<?php
namespace Corma\Repository;

use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidClassException;
use Psr\Container\ContainerInterface;

/**
 * Default object repository factory.
 *
 * MyNamespace\\MyClass is expected to have its' repository at MyNamespace\\Repository\\MyClassRepository.
 * If no repository is specified an instance of the base ObjectRepository class is returned.
 */
class ObjectRepositoryFactory implements ObjectRepositoryFactoryInterface
{
    /**
     * @var ObjectRepositoryInterface[]
     */
    private $repositories = [];

    /**
     * @var array
     */
    private $dependencies;

    /**
     * @var ContainerInterface|null
     */
    private $container;

    /**
     * @param ContainerInterface|null $container Dependency injection container to construct non-default repositories
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function getRepository(string $class): ?ObjectRepositoryInterface
    {
        if (isset($this->repositories[$class])) {
            return $this->repositories[$class];
        }

        [$namespace, $objectName] = $this->splitClassAndNamespace($class);
        $className = $this->getRepositoryClass($objectName, $namespace);
        $repository = $this->createRepository($className);
        if ($repository) {
            $this->repositories[$class] = $repository;
            return $repository;
        } elseif (class_exists($class)) {
            /** @var ObjectRepository $default */
            $default = $this->createRepository(ObjectRepository::class);
            $default->setClassName($class);
            return $this->repositories[$class] = $default;
        }

        throw new ClassNotFoundException("Cannot get repository for non-existent class '$class'");
    }

    /**
     * Strip namespace from fully qualified class names
     *
     * @param $objectClass
     * @return array
     */
    protected function splitClassAndNamespace($objectClass): array
    {
        $lastSlash = strrpos($objectClass, '\\');
        if ($lastSlash !== false) {
            return [substr($objectClass, 0, $lastSlash), substr($objectClass, $lastSlash+1)];
        } else {
            return [$objectClass, ''];
        }
    }


    protected function getRepositoryClass(string $objectName, string $namespace)
    {
        return "$namespace\\Repository\\{$objectName}Repository";
    }

    /**
     * Construct the repository and return
     *
     * @param $className
     * @return ObjectRepositoryInterface|null
     */
    protected function createRepository(string $className): ?ObjectRepositoryInterface
    {
        if (class_exists($className)) {
            if (!is_subclass_of($className, ObjectRepositoryInterface::class)) {
                throw new InvalidClassException("$className does not implement ObjectRepositoryInterface");
            }

            if ($this->container && $this->container->has($className)) {
                $repository = $this->container->get($className);
            } else {
                $reflection = new \ReflectionClass($className);
                /** @var ObjectRepositoryInterface $repository */
                $repository = $reflection->newInstanceArgs($this->dependencies);
            }

            $this->repositories[$className] = $repository;
            return $repository;
        }
        return null;
    }

    /**
     * @param array $repositoryDependencies Array of dependencies to pass into the default / base repository constructors
     */
    public function setDependencies(array $repositoryDependencies)
    {
        $this->dependencies = $repositoryDependencies;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
