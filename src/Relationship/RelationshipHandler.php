<?php

namespace Corma\Relationship;

use Corma\DBAL\Query\QueryBuilder;

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
     * @param QueryBuilder $qb Query Builder to add join to
     * @param string $fromAlias Alias of table being joined from
     * @param Relationship $relationship Relationship to join to
     * @param JoinType $type Type of join (inner, left, or right)
     * @param mixed|null $additional Additional information required by the relationship type to make the join as determined by the RelationshipHandler class
     * @return string The alias of the table joined to (composed of the first letter of each word in the property name)
     */
    public function join(QueryBuilder $qb, string $fromAlias, Relationship $relationship, JoinType $type = JoinType::INNER, mixed $additional = null): string;
}
