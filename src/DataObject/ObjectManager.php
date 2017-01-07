<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\TableConventionInterface;
use Doctrine\DBAL\Statement;

/**
 * Manages creation, hydration, and table name and id inspection for a particular class
 */
class ObjectManager
{
    /**
     * @var ObjectFactoryInterface
     */
    protected $factory;
    /**
     * @var ObjectHydratorInterface
     */
    protected $hydrator;
    /**
     * @var TableConventionInterface
     */
    protected $tableConvention;
    /**
     * @var ObjectIdentifierInterface
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var array
     */
    protected $dependencies;

    public function __construct(ObjectFactoryInterface $factory, ObjectHydratorInterface $hydrator, TableConventionInterface $tableConvention, ObjectIdentifierInterface $identifier, $className, array $dependencies = [])
    {
        $this->factory = $factory;
        $this->hydrator = $hydrator;
        $this->tableConvention = $tableConvention;
        $this->identifier = $identifier;
        $this->className = $className;
        $this->dependencies = $dependencies;
    }

    /**
     * @param array $data
     * @return object
     */
    public function create($data = [])
    {
        return $this->factory->create($this->className, $this->dependencies, $data);
    }

    /**
     * @param Statement|\PDOStatement $statement
     * @return object
     */
    public function fetchOne($statement)
    {
        return $this->factory->fetchOne($this->className, $statement, $this->dependencies);
    }

    /**
     * @param Statement|\PDOStatement $statement
     * @return object[]
     */
    public function fetchAll($statement)
    {
        return $this->factory->fetchAll($this->className, $statement, $this->dependencies);
    }

    /**
     * @param object $object
     * @param array $data
     * @return object
     */
    public function hydrate($object, array $data)
    {
        return $this->hydrator->hydrate($object, $data);
    }

    /**
     * @param object $object
     * @return array
     */
    public function extract($object)
    {
        return $this->hydrator->extract($object);
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->tableConvention->getTable($this->className);
    }

    /**
     * @return string
     */
    public function getIdColumn()
    {
        return $this->identifier->getIdColumn($this->className);
    }

    /**
     * @param $object
     * @return string
     */
    public function getId($object)
    {
        return $this->identifier->getId($object);
    }

    /**
     * Returns true if the object has not yet been persisted into the database
     *
     * @param $object
     * @return bool
     */
    public function isNew($object): bool
    {
        return $this->identifier->isNew($object);
    }

    /**
     * @param array $objects
     * @return string[]
     */
    public function getIds(array $objects)
    {
        return $this->identifier->getIds($objects);
    }

    /**
     * @param object $object
     * @param string $id
     * @return object
     */
    public function setId($object, $id)
    {
        return $this->identifier->setId($object, $id);
    }

    /**
     * @param object $object
     * @return object
     */
    public function setNewId($object)
    {
        return $this->identifier->setNewId($object);
    }
}