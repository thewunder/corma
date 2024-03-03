<?php

namespace Corma\Relationship;

/**
 * Represents a class that handles the saving and loading of a particular type of relationship
 */
interface RelationshipHandler
{
    /**
     * @return string Must be a class that implements RelationshipType
     */
    public static function getRelationshipClass(): string;

    public function load(array $objects, string $property): array;

    public function save(array $objects, string $property): void;
}