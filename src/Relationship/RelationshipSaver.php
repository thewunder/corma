<?php
namespace Corma\Relationship;

use Corma\DataObject\DataObjectInterface;
use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Corma\Util\Inflector;

/**
 * Makes saving foreign relationships easier, intended for use within ObjectRepository::save() or ObjectRepository::saveAll()
 * 
 * The supplied objects must be saved prior to calling these methods.
 * The repository is responsible for wrapping these method calls in a transaction.
 */
class RelationshipSaver
{
    /**
     * @var ObjectMapper
     */
    private $objectMapper;

    /**
     * @var Inflector
     */
    private $inflector;

    public function __construct(ObjectMapper $objectMapper)
    {
        $this->objectMapper = $objectMapper;
        $this->inflector = $objectMapper->getInflector();
    }

    /**
     * Saves a foreign relationship where a property on the supplied object references an id for another object.
     *
     * Can be used to save a one-to-one relationship or the "one" side of a one-to-many relationship.
     *
     * @param DataObjectInterface[] $objects
     * @param string $foreignIdColumn Property on this object that relates to the foreign tables id
     */
    public function saveOne(array $objects, $foreignIdColumn)
    {
        $getter = 'get' . $this->inflector->methodNameFromColumn($foreignIdColumn);
        /** @var DataObjectInterface[] $foreignObjectsByObjectId */
        $foreignObjectsByObjectId = [];
        foreach($objects as $object) {
            if(!method_exists($object, $getter)) {
                throw new MethodNotImplementedException("$getter must be defined on {$object->getClassName()} to save relationship");
            }
            
            $objectIdSetter = 'set' . $this->inflector->idColumnFromClass(get_class($object));
            $foreignObject = $object->{$getter}();
            if($foreignObject) {
                $foreignObjectsByObjectId[$object->getId()] = $foreignObject;
                if(method_exists($foreignObject, $objectIdSetter)) { // for true one-to-one relationships
                    $foreignObject->{$objectIdSetter}($object->getId());
                }
            }
        }
        
        $this->objectMapper->saveAll($foreignObjectsByObjectId);
        
        $idSetter = 'set' . $foreignIdColumn;
        $idGetter = 'get' . $foreignIdColumn;
        $objectsToUpdate = [];
        foreach($objects as $object) {
            if(!method_exists($object, $idSetter)) {
                throw new MethodNotImplementedException("$idSetter must be defined on {$object->getClassName()} to save relationship");
            }
            if(!method_exists($object, $idGetter)) {
                throw new MethodNotImplementedException("$idGetter must be defined on {$object->getClassName()} to save relationship");
            }
            
            if(isset($foreignObjectsByObjectId[$object->getId()])) {
                $foreignObject = $foreignObjectsByObjectId[$object->getId()];
                if($object->{$idGetter}() != $foreignObject->getId()) {
                    $object->{$idSetter}($foreignObject->getId());
                    $objectsToUpdate[] = $object;
                }
            }
        }
        $this->objectMapper->saveAll($objectsToUpdate);
    }

    /**
     * Saves a foreign relationship where a column on another object references the id for the supplied object.
     *
     * Used to save the "many" side of a one-to-many relationship.
     *
     * @param DataObjectInterface[] $objects 
     * @param string $className Class name of foreign objects to save
     * @param string $foreignColumn Property on foreign object that relates to this object id
     */
    public function saveMany(array $objects, $className, $foreignColumn = null)
    {
        //TODO: implement method
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     * 
     * This method does not insert or update the foreign objects.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     */
    public function saveManyToManyLinks(array $objects, $className, $linkTable, $idColumn = null, $foreignIdColumn = null)
    {
        //TODO: implement method
    }

    /**
     * Saves relationship data to a link table containing the id's of both objects.
     *
     * This method inserts / updates the foreign objects.
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     */
    public function saveManyToMany(array $objects, $className, $linkTable, $idColumn = null, $foreignIdColumn = null)
    {
        //TODO: Implement method
    }
}