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
    private array $repositories = [];
    private array $dependencies;

    /**
     * @param ContainerInterface|null $container Dependency injection container to construct non-default repositories
     */
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    public function getRepository(string $objectName): ?ObjectRepositoryInterface
    {
        if (isset($this->repositories[$objectName])) {
            return $this->repositories[$objectName];
        }

        [$namespace, $shortClass] = $this->splitClassAndNamespace($objectName);
        $className = $this->getRepositoryClass($shortClass, $namespace);
        $repository = $this->createRepository($className);
        if ($repository) {
            $this->repositories[$objectName] = $repository;
            return $repository;
        } elseif (class_exists($objectName)) {
            /** @var ObjectRepository $default */
            $default = $this->createRepository(ObjectRepository::class);
            $default->setClassName($objectName);
            return $this->repositories[$objectName] = $default;
        }

        throw new ClassNotFoundException("Cannot get repository for non-existent class '$objectName'");
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


    protected function getRepositoryClass(string $objectName, string $namespace): string
    {
        return "$namespace\\Repository\\{$objectName}Repository";
    }

    /**
     * Construct the repository and return
     *
     * @param string $className
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
