<?php

namespace Corma\Relationship;


/**
 * Represents a type of relationship that can be placed on a property
 *
 * Implementing classes must have the #[Attribute(Attribute::TARGET_PROPERTY)] attribute
 */
interface Relationship
{
    /**
     * Allows the relationship to set data from the property / class it was set on.
     */
    public function setReflectionData(\ReflectionProperty $property): void;

    /**
     * @return string The full class name of the object this relationship is defined on
     */
    public function getClass(): string;

    /**
     * @return string The full class name of the foreign object
     */
    public function getForeignClass(): string;

    /**
     * @return string The name of the property on this object that the relationship will be loaded on
     */
    public function getProperty(): string;

    public function setProperty(string $property): void;
}
