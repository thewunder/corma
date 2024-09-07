<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;
use Corma\DBAL\Query\QueryBuilder;

/**
 * Reads the RelationshipType type attribute and passes it to the proper handler.
 */
class RelationshipManager
{
    public const ALL = '*';

    /** @var RelationshipHandler[] $handlers Keyed by RelationshipType */
    private array $handlers = [];

    /**
     * @param RelationshipHandler[] $handlers
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * Adds or replaces a relationship handler
     */
    public function addHandler(RelationshipHandler $handler): void
    {
        $this->handlers[$handler::getRelationshipClass()] = $handler;
    }

    /**
     * @param array $objects Objects with relationships to save.
     * @param string ...$properties Property names with relationships to be saved, if '*' is passed, will save all relationships.
     * @throws InvalidAttributeException If the property does not exist or does not have a relationship attribute.
     */
    public function save(array $objects, string ...$properties): void
    {
        if (empty($objects) || empty($properties)) {
            return;
        }

        if ($properties[0] === self::ALL) {
            $this->saveAll($objects);
            return;
        }

        foreach ($properties as $property) {
            $relationshipType = $this->readAttribute(reset($objects), $property);
            $this->getHandler($relationshipType)->save($objects, $relationshipType);
        }
    }

    /**
     * @param object[] $objects Objects of the same class to load relationships on.
     * @param string ...$properties Property names with relationships to be loaded, if '*' is passed, will save all relationships.
     * @return object[]|object[][] The loaded objects keyed by id, or if multiple relationships were loaded, then by property name and id.
     * @throws InvalidAttributeException If the property does not exist or does not have a relationship attribute.
     */
    public function load(array $objects, string ...$properties): array
    {
        if (empty($objects)) {
            return [];
        }

        if ($properties[0] === self::ALL) {
            return $this->loadAll($objects);
        }

        $propertyCount = count($properties);
        $loadedObjectReturn = [];
        foreach ($properties as $property) {
            $relationship = $this->readAttribute(reset($objects), $property);
            $loadedObjects = $this->getHandler($relationship)->load($objects, $relationship);
            if ($propertyCount > 1) {
                $loadedObjectReturn[$property] = $loadedObjects;
            } else {
                $loadedObjectReturn += $loadedObjects;
            }
        }
        return $loadedObjectReturn;
    }

    /**
     * Saves all relationships on defined on the passed in object.
     *
     * @param object[] $objects Objects to save relationships on.
     */
    public function saveAll(array $objects): void
    {
        if (empty($objects)) {
            return;
        }

        $relationships = $this->readAllRelationships(reset($objects));
        foreach ($relationships as $relationship) {
            $this->getHandler($relationship)->save($objects, $relationship);
        }
    }

    /**
     * @param object[] $objects Objects of the same class to load relationships on.
     * @return object[]|object[][] The loaded objects keyed by id, or if multiple relationships were loaded, then by property name and id.
     */
    public function loadAll(array $objects): array
    {
        if (empty($objects)) {
            return [];
        }

        $relationships = $this->readAllRelationships(reset($objects));
        $relationshipCount = count($relationships);
        $loadedObjectReturn = [];
        foreach ($relationships as $relationship) {
            $loadedObjects = $this->getHandler($relationship)->load($objects, $relationship);
            if ($relationshipCount > 1) {
                $loadedObjectReturn[$relationship->getProperty()] = $loadedObjects;
            } else {
                $loadedObjectReturn += $loadedObjects;
            }
        }
        return $loadedObjectReturn;
    }

    /**
     * Join to a relationship defined via property attributes.
     * @return string
     */
    public function join(QueryBuilder $qb, string $class, string $property, string $alias = 'main', JoinType $type = JoinType::INNER, mixed $additional = null): string
    {
        $relationship = $this->readAttribute($class, $property);
        $handler = $this->getHandler($relationship);
        return $handler->join($qb, $alias, $relationship, $type, $additional);
    }

    public function getHandler(Relationship $relationship): RelationshipHandler
    {

        $relationshipClass = $relationship::class;
        $handler = $this->handlers[$relationshipClass] ?? null;
        if (!$handler) {
            throw new InvalidAttributeException('Missing handler for '.$relationshipClass);
        }
        return $handler;

    }

    public function readAttribute(object|string $objectOrClass, string $property): Relationship
    {
        $property = new \ReflectionProperty($objectOrClass, $property);
        $attributes = $property->getAttributes(Relationship::class, \ReflectionAttribute::IS_INSTANCEOF);
        return $this->getRelationship($property, $attributes);
    }

    /**
     * @param object|string $objectOrClass
     * @return Relationship[]
     */
    public function readAllRelationships(object|string $objectOrClass): array
    {
        $class = new \ReflectionClass($objectOrClass);
        $relationships = [];
        foreach ($class->getProperties() as $property) {
            $attributes = $property->getAttributes(Relationship::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($attributes)) {
                $relationships[] = $this->getRelationship($property, $attributes);
            }
        }
        return $relationships;
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return Relationship
     */
    private function getRelationship(\ReflectionProperty $property, array $attributes): Relationship
    {
        $attributeCount = count($attributes);

        if ($attributeCount == 0) {
            throw new InvalidAttributeException('No relationship attribute found on ' . $property);
        }
        if ($attributeCount > 1) {
            throw new InvalidAttributeException('Only one relation type attribute can be applied to a property');
        }

        /** @var Relationship $relationship */
        $relationship = $attributes[0]->newInstance();
        $relationship->setReflectionData($property);
        return $relationship;
    }
}
