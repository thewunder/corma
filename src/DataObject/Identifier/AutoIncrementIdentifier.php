<?php
namespace Corma\DataObject\Identifier;

use Corma\DataObject\TableConvention\TableConventionInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

/**
 * Sets the id for new objects from the database sequence / auto increment id
 */
class AutoIncrementIdentifier extends AnnotationCustomizableIdentifier
{
    /**
     * @var QueryHelper
     */
    private $queryHelper;
    /**
     * @var TableConventionInterface
     */
    private $convention;

    public function __construct(Inflector $inflector, ReaderInterface $reader = null, QueryHelper $queryHelper, TableConventionInterface $convention)
    {
        parent::__construct($inflector, $reader);
        $this->queryHelper = $queryHelper;
        $this->convention = $convention;
    }

    public function setNewId($object)
    {
        $table = $this->convention->getTable($object);
        $this->setId($object, $this->queryHelper->getLastInsertId($table, $this->getIdColumn($object)));
    }
}