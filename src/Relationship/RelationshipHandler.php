<?php

namespace Corma\Relationship;

use Doctrine\DBAL\Query\QueryBuilder;

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
    public function load(array $objects, Relationship $relationship): array;

    /**
     * @param object[] $objects Objects to load a relationship on
     */
    public function save(array $objects, Relationship $relationship): void;

    /**
     * Add a join to the relationship to the specified to the provided query builder
     * @return string The alias of the table joined to
     */
    public function join(QueryBuilder $qb, string $fromAlias, Relationship $relationship, JoinType $type = JoinType::INNER): string;
}
