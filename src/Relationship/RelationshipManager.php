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

    public function getHandler(RelationshipType $relationshipType): RelationshipHandler
    {

        $relationshipClass = $relationshipType::class;
        $handler = $this->handlers[$relationshipClass] ?? null;
        if (!$handler) {
            throw new InvalidAttributeException('Missing handler for '.$relationshipClass);
        }
        return $handler;

    }

    public function readAttribute(object|string $objectOrClass, string $property): ?RelationshipType
    {
        $property = new \ReflectionProperty($objectOrClass, $property);
        $attributes = $property->getAttributes(RelationshipType::class, \ReflectionAttribute::IS_INSTANCEOF);
        $attributeCount = count($attributes);
        if ($attributeCount > 1) {
            throw new InvalidAttributeException('Only one relation type attribute can be applied to a property');
        }
        if ($attributeCount > 0) {
            /** @var RelationshipType $relationship */
            $relationship = $attributes[0]->newInstance();
            $relationship->setReflectionData($property);
            return $relationship;
        }
        return null;
    }
}
