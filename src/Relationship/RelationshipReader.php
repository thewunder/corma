<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;

/**
 * Reads the RelationshipType type attribute and passes it to the proper handler.
 */
final class RelationshipReader
{

    /** @var RelationshipHandler[] */
    private array $handlers = [];

    /**
     * @param RelationshipHandler[] $handlers
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler::getRelationshipClass()] = $handler;
        }
    }

    public function getHandler(object|string $objectOrClass, string $property): ?RelationshipHandler
    {
        $relationshipType = $this->readAttribute($objectOrClass, $property);
        if ($relationshipType) {
            $relationshipClass = $relationshipType::class;
            $handler = $this->handlers[$relationshipClass] ?? null;
            if (!$handler) {
                throw new InvalidAttributeException('Missing handler for '.$relationshipClass);
            }
            return $handler;
        }
        return null;
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
