<?php

namespace Corma\Relationship;


use Corma\Exception\InvalidAttributeException;

/**
 * A one-to-one relationship (or the one side of a one-to-many), where an id on this table references a foreign table.
 *
 * Must be set on a property with a class type hint, or the class must be provided.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToOne extends BaseRelationship
{
    protected const AUTO = 'auto';

    public function __construct(
        string $className = self::AUTO,
        /** @var string|null $foreignIdColumn Property on this object that relates to the foreign tables id */
        private readonly ?string $foreignIdColumn = null
    )
    {
        if ($className !== self::AUTO) {
            parent::__construct($className);
        } else {
            $this->foreignClass = $className;
        }
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        if ($this->foreignClass === self::AUTO) {
            if (!$property->hasType()) {
                throw new InvalidAttributeException('To automatically infer the relationship class, it must have a type hint');
            }
            $className = $property->getType()->getName();
            if (!class_exists($className)) {
                throw new InvalidAttributeException('Only valid classes can be used to automatically infer the relationship type');
            }
            $this->foreignClass = $className;
        }

        parent::setReflectionData($property);
    }

    public function getForeignIdColumn(): ?string
    {
        return $this->foreignIdColumn;
    }
}
