<?php

namespace Corma\Relationship;


use Corma\Exception\InvalidAttributeException;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToOne extends RelationshipType
{
    private const AUTO = 'auto';

    public function __construct(string $className = self::AUTO,
        public readonly ?string $foreignIdColumn = null
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
}
