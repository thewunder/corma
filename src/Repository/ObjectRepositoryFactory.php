<?php
namespace Corma\Repository;

use Corma\Exception\ClassNotFoundException;
use Corma\Exception\InvalidArgumentException;
use Corma\Exception\InvalidClassException;
use Corma\Util\QueryHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

    public function getRepository($objectClass)
    {
        if(isset($this->repositories[$objectClass])) {
            return $this->repositories[$objectClass];
        }

        foreach($this->namespaces as $namespace) {
            $className = $this->getRepositoryClass($objectClass, $namespace);
            if(class_exists($className)) {
                if(!class_implements($className, ObjectRepositoryInterface::class)) {
                    throw new InvalidClassException("$className does not implement ObjectRepositoryInterface");
                }

                $reflection = new \ReflectionClass($className);
                /** @var ObjectRepositoryInterface $repository */
                $repository = $reflection->newInstanceArgs($this->dependencies);
                return $repository;
            }
        }

        throw new ClassNotFoundException("Could not find repository class for $objectClass");
    }

    protected function getRepositoryClass($objectClass, $namespace)
    {
        return "$namespace\\Repository\\{$objectClass}Repository";
    }
}