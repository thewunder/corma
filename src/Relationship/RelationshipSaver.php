<?php
namespace Corma\Relationship;

use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

/**
 * Makes saving foreign relationships easier, intended for use within ObjectRepository::save() or ObjectRepository::saveAll()
 *
 * The supplied objects must be saved prior to calling these methods.
 * The repository is responsible for wrapping these method calls in a transaction.
 *
 * @deprecated Use RelationshipManager instead
 */
class RelationshipSaver
{
    private readonly Inflector $inflector;
    private readonly RelationshipManager $relationshipManager;

    public function __construct(private readonly ObjectMapper $objectMapper)
    {
        $this->inflector = $objectMapper->getInflector();
        $this->relationshipManager = $this->objectMapper->getRelationshipManager();
    }

    /**
     * Saves a foreign relationship where a property on the supplied object references an id for another object.
     *
     * Can be used to save a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign object to load
     * @param string|null $foreignIdColumn Property on this object that relates to the foreign tables id
     * @param string|null $getter Name of getter method on objects
     */
    public function saveOne(array $objects, string $className, ?string $foreignIdColumn = null, ?string $getter = null): void
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);
        $property = $this->inferProperty($getter, $foreignIdColumn);

        $attribute = new OneToOne($className, $foreignIdColumn);
        $attribute->setProperty($property);

        $this->relationshipManager->getHandler($attribute)->save($objects, $attribute);
    }

    /**
     * Saves a foreign relationship where a column on another object references the id for the supplied object.
     *
     * Used to save the "many" side of a one-to-many relationship.
     *
     * Missing objects will be deleted by default.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $foreignColumn Property on foreign object that relates to this object id
     * @param boolean $deleteMissing Set to false to leave objects alone if missing
     * @throws \Throwable
     */
    public function saveMany(array $objects, string $className, ?string $foreignObjectGetter = null, ?string $foreignColumn = null, bool $deleteMissing = true): void
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);
        $property = $this->inferProperty($foreignObjectGetter, $foreignIdColumn);


        $attribute = new OneToMany($className, $foreignColumn, $deleteMissing, $foreignObjectGetter);
        $attribute->setProperty($property);

        $this->relationshipManager->getHandler($attribute)->save($objects, $attribute);
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method does not insert or update the foreign objects.
     * Missing foreign objects will be removed from the link table.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @throws \Throwable
     */
    public function saveManyToManyLinks(
        array $objects,
        string $className,
        string $linkTable,
        ?string $foreignObjectGetter = null,
        ?string $idColumn = null,
        ?string $foreignIdColumn = null
    ): void
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);

        $property = $this->inferProperty($foreignObjectGetter, $foreignIdColumn);

        $attribute = new ManyToMany($className, $linkTable, $idColumn, $foreignIdColumn, true);
        $attribute->setProperty($property);

        $this->relationshipManager->getHandler($attribute)->save($objects, $attribute);
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method inserts / updates the foreign objects.
     * Missing foreign objects will be removed from the link table, but not deleted.
     *
     * @param object[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string|null $foreignObjectGetter Name of getter to retrieve foreign objects
     * @param string|null $idColumn Column on link table = the id on this object
     * @param string|null $foreignIdColumn Column on link table = the id on the foreign object table
     * @throws \Throwable
     */
    public function saveManyToMany(
        array $objects,
        string $className,
        string $linkTable,
        ?string $foreignObjectGetter = null,
        ?string $idColumn = null,
        ?string $foreignIdColumn = null
    ): void
    {
        $foreignIdColumn ??= $this->inflector->idColumnFromClass($className);

        $property = $this->inferProperty($foreignObjectGetter, $foreignIdColumn);

        $attribute = new ManyToMany($className, $linkTable, $idColumn, $foreignIdColumn);
        $attribute->setProperty($property);

        $this->relationshipManager->getHandler($attribute)->save($objects, $attribute);
    }

    private function inferProperty(?string $getter, string $foreignIdColumn): string
    {
        if ($getter) {
            $property = lcfirst(str_replace('get', '', $getter));
        } else {
            $property = str_replace(['Id', '_id'], '', $foreignIdColumn);
        }
        return $property;
    }
}
