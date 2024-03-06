<?php

namespace Corma\Relationship;


use Corma\Exception\BadMethodCallException;
use Corma\Exception\InvalidAttributeException;

abstract class BaseRelationship implements Relationship
{
    protected string $className;
    protected ?string $property = null;

    /**
     * @param string $className The class of the object this property relates to
     */
    public function __construct(string $className)
    {
        if (!class_exists($className)) {
            throw new InvalidAttributeException('Invalid class name');
        }
        $this->className = $className;
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        $this->property = $property->getName();
    }

    public function getClassName(): string
    {
        return $this->className;
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
