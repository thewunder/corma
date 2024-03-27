<?php

namespace Corma\Relationship;


use Corma\Exception\BadMethodCallException;
use Corma\Exception\InvalidAttributeException;

abstract class BaseRelationship implements Relationship
{
    protected string $foreignClass;
    protected ?string $property = null;
    protected ?string $class = null;

    /**
     * @param string $className The class of the object this property relates to
     */
    public function __construct(string $className)
    {
        if (!class_exists($className)) {
            throw new InvalidAttributeException('Invalid class name');
        }
        $this->foreignClass = $className;
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        $this->property = $property->getName();
        $this->class = $property->getDeclaringClass()->getName();
    }

    public function getForeignClass(): string
    {
        return $this->foreignClass;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function setProperty(string $property): void
    {
        if ($this->property !== null) {
            throw new BadMethodCallException('Override of property is not supported');
        }
        $this->property = $property;
    }
}
