<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;

/**
 * A relationship where the id of the object is referenced in a column of a foreign table.
 *
 * Must be set on an array property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OneToMany extends BaseRelationship
{
    public function __construct(
        string $className,
        /** @var ?string $foreignColumn Property on foreign object that relates to this object id */
        private readonly ?string $foreignColumn = null,
        /** @var bool $deleteMissing Set to false in order to leave objects alone when they are removed from the array. */
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
