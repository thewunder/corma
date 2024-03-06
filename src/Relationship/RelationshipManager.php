<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;

/**
 * Reads the RelationshipType type attribute and passes it to the proper handler.
 */
final class RelationshipManager
{
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

    public function save(array $objects, string $property): void
    {
        if (empty($objects)) {
            return;
        }

        $relationshipType = $this->readAttribute(reset($objects), $property);
        $this->getHandler($relationshipType)->save($objects, $relationshipType);
    }

    public function load(array $objects, string $property): array
    {
        if (empty($objects)) {
            return [];
        }

        $relationshipType = $this->readAttribute(reset($objects), $property);
        return $this->getHandler($relationshipType)->load($objects, $relationshipType);
    }

    /**
     * @param object[] $objects
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
     * @param object[] $objects
     */
    public function loadAll(array $objects): void
    {
        if (empty($objects)) {
            return;
        }

        $relationships = $this->readAllRelationships(reset($objects));
        foreach ($relationships as $relationship) {
            $this->getHandler($relationship)->load($objects, $relationship);
        }
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
        $relationship = $this->getRelationship($property, $attributes);
        return $relationship;
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
     * @param \ReflectionProperty $property
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
