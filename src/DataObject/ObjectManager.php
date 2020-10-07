<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\TableConventionInterface;
use Doctrine\DBAL\Driver\ResultStatement;

/**
 * Manages creation, hydration, and table name and id inspection for a particular class
 */
class ObjectManager
{
    /**
     * @var ObjectHydratorInterface
     */
    protected $hydrator;
    /**
     * @var ObjectIdentifierInterface
     */
    protected $identifier;
    /**
     * @var TableConventionInterface
     */
    protected $tableConvention;
    /**
     * @var ObjectFactoryInterface
     */
    protected $factory;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var array
     */
    protected $dependencies;

    public function __construct(ObjectHydratorInterface $hydrator, ObjectIdentifierInterface $identifier, TableConventionInterface $tableConvention, ObjectFactoryInterface $factory, string $className, array $dependencies = [])
    {
        $this->hydrator = $hydrator;
        $this->identifier = $identifier;
        $this->tableConvention = $tableConvention;
        $this->factory = $factory;
        $this->className = $className;
        $this->dependencies = $dependencies;
    }

    /**
     * Creates a new instance of object, optionally populated with the supplied data
     *
     * @param array $data
     * @return object
     */
    public function create($data = [])
    {
        return $this->factory->create($this->className, $data, $this->dependencies);
    }

    /**
     * Retrieves a single object from the database
     *
     * @param ResultStatement|\PDOStatement $statement
     * @return object
     */
    public function fetchOne($statement)
    {
        return $this->factory->fetchOne($this->className, $statement, $this->dependencies);
    }

    /**
     * Retrieves multiple objects from the database
     *
     * @param ResultStatement|\PDOStatement $statement
     * @return object[]
     */
    public function fetchAll($statement): array
    {
        return $this->factory->fetchAll($this->className, $statement, $this->dependencies);
    }

    /**
     * Sets the supplied data on to the object.
     *
     * @param object $object
     * @param array $data
     * @return object
     */
    public function hydrate(object $object, array $data)
    {
        return $this->hydrator->hydrate($object, $data);
    }

    /**
     * Extracts all scalar data from the object
     *
     * @param object $object
     * @return array
     */
    public function extract(object $object): array
    {
        return $this->hydrator->extract($object);
    }

    /**
     * Gets the name of the database table
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->tableConvention->getTable($this->className);
    }

    /**
     * @return string Database column name containing the identifier for the object
     */
    public function getIdColumn(): string
    {
        return $this->identifier->getIdColumn($this->className);
    }

    /**
     * @param object $object
     * @return string
     */
    public function getId(object $object): ?string
    {
        return $this->identifier->getId($object);
    }

    /**
     * Returns true if the object has not yet been persisted into the database
     *
     * @param object $object
     * @return bool
     */
    public function isNew(object $object): bool
    {
        return $this->identifier->isNew($object);
    }

    /**
     * Gets the primary key for the supplied objects
     *
     * @param array $objects
     * @return string[]
     */
    public function getIds(array $objects): array
    {
        return $this->identifier->getIds($objects);
    }

    /**
     * Sets the primary key on the object
     *
     * @param object $object
     * @param string $id
     * @return object
     */
    public function setId($object, $id): object
    {
        return $this->identifier->setId($object, $id);
    }

    /**
     * @param object $object
     * @return object
     */
    public function setNewId(object $object): object
    {
        return $this->identifier->setNewId($object);
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
