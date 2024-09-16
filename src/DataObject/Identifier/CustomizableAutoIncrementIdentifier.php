<?php
namespace Corma\DataObject\Identifier;

use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\Inflector;

/**
 * Sets the id for new objects from the database sequence / auto increment id
 */
class CustomizableAutoIncrementIdentifier extends CustomizableIdentifier
{
    public function __construct(Inflector $inflector, private readonly QueryHelperInterface $queryHelper)
    {
        parent::__construct($inflector);
    }

    public function setNewId(object $object): object
    {
        return $this->setId($object, $this->queryHelper->getLastInsertId());
    }

    public function isNew(object $object): bool
    {
        return !$this->getId($object);
    }
}
