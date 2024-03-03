<?php

namespace Corma\Relationship;

class OneToOneHandler implements RelationshipHandler
{

    public static function getRelationshipClass(): string
    {
        return OneToOne::class;
    }

    public function load(array $objects, string $property): array
    {
        // TODO: Implement load() method.
    }

    public function save(array $objects, string $property): void
    {
        // TODO: Implement save() method.
    }
}
