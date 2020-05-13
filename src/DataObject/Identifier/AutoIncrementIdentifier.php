<?php
namespace Corma\DataObject\Identifier;

use Corma\DataObject\TableConvention\TableConventionInterface;
use Corma\QueryHelper\QueryHelper;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\Inflector;

/**
 * Sets the id for new objects from the database sequence / auto increment id
 */
class AutoIncrementIdentifier extends BaseIdentifier
{
    /**
     * @var QueryHelper
     */
    private $queryHelper;
    /**
     * @var TableConventionInterface
     */
    private $convention;

    public function __construct(Inflector $inflector, QueryHelperInterface $queryHelper, TableConventionInterface $convention)
    {
        parent::__construct($inflector);
        $this->queryHelper = $queryHelper;
        $this->convention = $convention;
    }

    public function setNewId($object)
    {
        $table = $this->convention->getTable($object);
        $this->setId($object, $this->queryHelper->getLastInsertId($table, $this->getIdColumn($object)));
    }

    public function isNew($object): bool
    {
        return !$this->getId($object);
    }
}
