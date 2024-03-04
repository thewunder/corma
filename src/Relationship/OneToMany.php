<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToMany extends RelationshipType
{
    public function __construct(string $className,
                                private readonly ?string $foreignColumn = null,
                                private readonly bool $deleteMissing = true)
    {
        parent::__construct($className);
    }

    public function getForeignColumn(): ?string
    {
        return $this->foreignColumn;
    }

    public function deleteMissing(): bool
    {
        return $this->deleteMissing;
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        if ($property->getType()->getName() !== 'array') {
            throw new InvalidAttributeException('Only array properties can be used for a one-to-many relationship');
        }
        parent::setReflectionData($property);
    }
}
