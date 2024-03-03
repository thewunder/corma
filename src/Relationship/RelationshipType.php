<?php

namespace Corma\Relationship;


use Corma\Exception\InvalidAttributeException;

/**
 * Represents a type of relationship that can be placed on a property
 *
 * Inheritors must have the #[Attribute(Attribute::TARGET_PROPERTY)] attribute
 */
abstract class RelationshipType
{
    public string $className;
    public string $property;
    public function __construct(string $className)
    {
        if (!class_exists($className)) {
            throw new InvalidAttributeException('Invalid class name');
        }
        $this->className = $className;
    }

    /**
     * Allows the relationship to set data from the property / class it was set on.
     */
    public function setReflectionData(\ReflectionProperty $property): void
    {
        $this->property = $property->getName();
    }
}
