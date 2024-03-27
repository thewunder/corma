<?php

namespace Corma\Relationship;

use Corma\Exception\BadMethodCallException;

/**
 * A one-to-one relationship where the class is determined by a class column containing a partial class name.
 * The default behavior is to resolve the partial class using the namespace of the class this is being used.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Polymorphic implements Relationship
{
    private ?string $property = null;

    public function __construct(
        /** @var ?string $namespace The base namespace of all classes stored in this property */
        private ?string $namespace = null
    )
    {
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        $this->property = $property->getName();
        $this->namespace = $property->getDeclaringClass()->getNamespaceName();
    }

    /**
     * @return string The namespace of all classes loaded / saved by this relationship
     */
    public function getForeignClass(): string
    {
        return $this->namespace;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function setProperty(string $property): void
    {
        throw new BadMethodCallException('Overriding the property is only supported by legacy supported relationships');
    }
}
