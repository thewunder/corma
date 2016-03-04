<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;
use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidArgumentException;
use Corma\Exception\InvalidClassException;

/**
 * Default object repository factory, loads repositories from one or more namespaces
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
    private $namespaces;
    /**
     * @var array
     */
    private $dependencies;

    public function __construct(array $namespaces, array $dependencies)
    {
        if(empty($namespaces)) {
            throw new InvalidArgumentException('At least one data object repository namespace must be specified');
        }
        $this->namespaces = $namespaces;
        $this->dependencies = $dependencies;
    }

    public function getRepository($objectName)
    {
        $objectName = $this->getObjectName($objectName);
        if(isset($this->repositories[$objectName])) {
            return $this->repositories[$objectName];
        }

        foreach($this->namespaces as $namespace) {
            $className = $this->getRepositoryClass($objectName, $namespace);
            $repository = $this->createRepository($className);
            if($repository) {
                $this->repositories[$objectName] = $repository;
                return $repository;
            } else {
                $objectClass = "$namespace\\$objectName";
                if(class_exists($objectClass) && is_subclass_of($objectClass, DataObjectInterface::class)) {
                    /** @var ObjectRepository $default */
                    $default = $this->createRepository(ObjectRepository::class);
                    $default->setClassName($objectClass);
                    return $this->repositories[$objectName] = $default;
                }
            }
        }

        throw new ClassNotFoundException("Could not find repository class for $objectName");
    }

    /**
     * Strip namespace from fully qualified class names
     *
     * @param $objectClass
     * @return string
     */
    protected function getObjectName($objectClass)
    {
        $lastSlash = strrpos($objectClass, '\\');
        if($lastSlash !== false) {
            return substr($objectClass, $lastSlash+1);
        } else {
            return $objectClass;
        }
    }


    protected function getRepositoryClass($objectName, $namespace)
    {
        return "$namespace\\Repository\\{$objectName}Repository";
    }

    /**
     * Construct the repository and return
     *
     * @param $className
     * @return ObjectRepositoryInterface|null
     */
    protected function createRepository($className)
    {
        if(class_exists($className)) {
            if(!is_subclass_of($className, ObjectRepositoryInterface::class)) {
                throw new InvalidClassException("$className does not implement ObjectRepositoryInterface");
            }

            $reflection = new \ReflectionClass($className);
            /** @var ObjectRepositoryInterface $repository */
            $repository = $reflection->newInstanceArgs($this->dependencies);

            $this->repositories[$className] = $repository;
            return $repository;
        }
        return null;
    }
}
