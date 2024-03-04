<?php
namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

/**
 * Loads foreign relationships
 */
class RelationshipLoader
{
    private readonly Inflector $inflector;
    private readonly RelationshipManager $relationshipManager;

    public function __construct(private readonly ObjectMapper $objectMapper)
    {
        $this->inflector = $objectMapper->getInflector();
        $this->relationshipManager = $this->objectMapper->getRelationshipManager();
    }

    /**
     * Loads a foreign relationship where a property on the supplied objects references an id for another object.
     *
     * Can be used to load a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign object to load
     * @param string|null $foreignIdColumn Property on this object that relates to the foreign tables id
     * @param string|null $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     */
    public function loadOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);
        $property = $this->inferProperty($setter, $foreignIdColumn);

        $attribute = new OneToOne($className, $foreignIdColumn);
        $attribute->setProperty($property);

        return $this->relationshipManager->getHandler($attribute)->load($objects, $attribute);
    }

    /**
     * Loads a foreign relationship where a column on another object references the id for the supplied objects.
     *
     * Used to load the "many" side of a one-to-many relationship.
     *
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign objects to load
     * @param string|null $foreignColumn Property on foreign object that relates to this object id
     * @param string|null $setter Name of setter method on objects
     * @return array|object[] Loaded objects keyed by id
     */
    public function loadMany(array $objects, string $className, ?string $foreignColumn = null, ?string $setter = null): array
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass(reset($objects)::class);

        $property = $this->inferProperty($setter, $foreignIdColumn);

        $attribute = new OneToMany($className, $foreignColumn);
        $attribute->setProperty($property);

        return $this->relationshipManager->getHandler($attribute)->load($objects, $attribute);
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects
     *
     * @param object[] $objects Data objects of the same class
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @param string|null $setter Name of setter method on objects
     * @return object[] Loaded objects keyed by id
     * @throws \Doctrine\DBAL\Exception
     */
    public function loadManyToMany(array $objects, string $className, string $linkTable, ?string $idColumn = null, ?string $foreignIdColumn = null, ?string $setter = null): array
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);

        $property = $this->inferProperty($setter, $foreignIdColumn);

        $attribute = new ManyToMany($className, $linkTable, $idColumn, $foreignIdColumn);
        $attribute->setProperty($property);

        return $this->relationshipManager->getHandler($attribute)->load($objects, $attribute);
    }

    private function inferProperty(?string $setter, string $foreignIdColumn): string
    {
        if ($setter) {
            $property = lcfirst(str_replace('set', '', $setter));
        } else {
            $property = str_replace(['Id', '_id'], '', $foreignIdColumn);
        }
        return $property;
    }
}
