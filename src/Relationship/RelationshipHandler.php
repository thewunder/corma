<?php

namespace Corma\Relationship;

/**
 * Represents a class that handles the saving and loading of a particular type of relationship
 */
interface RelationshipHandler
{
    /**
     * @return string Must be a class that extends RelationshipType
     */
    public static function getRelationshipClass(): string;

    /**
     * @param object[] $objects Objects to load a relationship on
     */
    public function load(array $objects, RelationshipType $relationship): array;

    /**
     * @param object[] $objects Objects to load a relationship on
     */
    public function save(array $objects, RelationshipType $relationship): void;
}
