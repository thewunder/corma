<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\TableConventionInterface;
use Doctrine\DBAL\Result;

/**
 * Manages creation, hydration, and table name and id inspection for a particular class
 */
class ObjectManager
{
    public function __construct(protected ObjectHydratorInterface $hydrator, protected ObjectIdentifierInterface $identifier,
                                protected TableConventionInterface $tableConvention, protected ObjectFactoryInterface $factory,
                                protected string $className, protected array $dependencies = [])
    {
    }

    /**
     * Creates a new instance of object, optionally populated with the supplied data
     *
     * @return object
     */
    public function create(array $data = []): object
    {
        return $this->factory->create($this->className, $data, $this->dependencies);
    }

    /**
     * Retrieves a single object from the database
     *
     * @return object|null
     */
    public function fetchOne(Result $result): ?object
    {
        return $this->factory->fetchOne($this->className, $result, $this->dependencies);
    }

    /**
     * Retrieves multiple objects from the database
     *
     * @return object[]
     */
    public function fetchAll(Result $result): array
    {
        return $this->factory->fetchAll($this->className, $result, $this->dependencies);
    }

    /**
     * Sets the supplied data on to the object.
     *
     * @return object
     */
    public function hydrate(object $object, array $data): object
    {
        return $this->hydrator->hydrate($object, $data);
    }

    /**
     * Extracts all scalar data from the object
     *
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

    public function getId(object $object): string|int|null
    {
        return $this->identifier->getId($object);
    }

    /**
     * Returns true if the object has not yet been persisted into the database
     *
     * @return bool
     */
    public function isNew(object $object): bool
    {
        return $this->identifier->isNew($object);
    }

    /**
     * Gets the primary key for the supplied objects
     *
     * @return string[]
     */
    public function getIds(array $objects): array
    {
        return $this->identifier->getIds($objects);
    }

    /**
     * Sets the primary key on the object
     *
     * @param string|int $id
     * @return object
     */
    public function setId(object $object, string|int $id): object
    {
        return $this->identifier->setId($object, $id);
    }

    /**
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
