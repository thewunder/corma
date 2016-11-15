<?php
namespace Corma\DataObject\Factory;

use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Doctrine\DBAL\Driver\Statement;

class PdoObjectFactory implements ObjectFactoryInterface
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

    public function create($class, array $dependencies, array $data = [])
    {
        if(empty($dependencies)) {
            $object = new $class;
        } else {
            $reflectionClass = $this->getReflectionClass($class);
            $object = $reflectionClass->newInstanceArgs($dependencies);
        }

        if(!empty($data)) {
            $this->hydrator->hydrate($object, $data);
        }
        return $object;
    }

    public function fetchAll($class, $statement, array $dependencies)
    {
        return $statement->fetchAll(\PDO::FETCH_CLASS, $class, $dependencies);
    }

    /**
     * Retrieves a single item from select statement, hydrated, and with dependencies
     *
     * @param string $class
     * @param Statement $statement
     * @param array $dependencies
     * @return object
     */
    public function fetchOne($class, $statement, array $dependencies)
    {
        $statement->setFetchMode(\PDO::FETCH_CLASS, $class, $dependencies);
        return $statement->fetch();
    }

    protected function getReflectionClass($class)
    {
        if(isset($this->reflectionClasses[$class])) {
            return $this->reflectionClasses[$class];
        }
        return $this->reflectionClasses[$class] = new \ReflectionClass($class);
    }
}