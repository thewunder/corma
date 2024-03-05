<?php

namespace Corma\Relationship;

use Corma\Exception\InvalidAttributeException;

/**
 * A relationship where two tables are linked together by a third link table
 * that contains the id of this table and a foreign table id.
 *
 * Must be set on an array property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ManyToMany extends RelationshipType
{
    public function __construct(
        string $className,
        /** @var $linkTable string Table that links two objects together */
        private readonly string $linkTable,
        /** @var $idColumn ?string Column on link table = the id on this object */
        private readonly ?string $idColumn = null,
        /** @var $foreignIdColumn ?string Column on link table = the id on the foreign object table */
        private readonly ?string $foreignIdColumn = null,
        /** @var $shallow bool If true will only save to the link table, and not the foreign objects */
        private readonly bool $shallow = false
    )
    {
        parent::__construct($className);
    }

    public function getLinkTable(): string
    {
        return $this->linkTable;
    }

    public function getIdColumn(): ?string
    {
        return $this->idColumn;
    }

    public function getForeignIdColumn(): ?string
    {
        return $this->foreignIdColumn;
    }

    public function isShallow(): bool
    {
        return $this->shallow;
    }

    public function setReflectionData(\ReflectionProperty $property): void
    {
        if ($property->getType()->getName() !== 'array') {
            throw new InvalidAttributeException('Only array properties can be used for a one-to-many relationship');
        }
        parent::setReflectionData($property);
    }
}
