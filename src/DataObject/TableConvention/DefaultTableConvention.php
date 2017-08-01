<?php
namespace Corma\DataObject\TableConvention;

use Corma\Util\Inflector;
use Doctrine\Common\Inflector\Inflector as DoctrineInflector;

class DefaultTableConvention implements TableConventionInterface
{
    /**
     * @var Inflector
     */
    protected $inflector;


    public function __construct(Inflector $inflector)
    {
        $this->inflector = $inflector;
    }

    /**
     * @param string|object $classOrObject
     * @return string The database table name
     */
    public function getTable($classOrObject): string
    {
        $class = $this->inflector->getShortClass($classOrObject);
        return DoctrineInflector::tableize(DoctrineInflector::pluralize($class));
    }
}
