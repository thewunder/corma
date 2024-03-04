<?php

namespace Corma\Relationship;


use Corma\Exception\InvalidAttributeException;

/**
 * A one-to-one relationship (or the one side of a one-to-many)
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToOne extends RelationshipType
{
    protected const AUTO = 'auto';

    public function __construct(string $className = self::AUTO,
        private readonly ?string $foreignIdColumn = null
    )
    {
        if ($className !== self::AUTO) {
            parent::__construct($className);
        } else {
            $this->className = $className;
        }
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        if ($this->className === self::AUTO) {
            if (!$property->hasType()) {
                throw new InvalidAttributeException('To automatically infer the relationship class, it must have a type hint');
            }
            $className = $property->getType()->getName();
            if (!class_exists($className)) {
                throw new InvalidAttributeException('Only valid classes can be used to automatically know the relationship type');
            }
            $this->className = $className;
        }

        parent::setReflectionData($property);
    }

    public function getForeignIdColumn(): ?string
    {
        return $this->foreignIdColumn;
    }
}
