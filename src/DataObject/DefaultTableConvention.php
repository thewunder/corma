<?php
namespace Corma\DataObject;

use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;
use Doctrine\Common\Inflector\Inflector as DoctrineInflector;

class DefaultTableConvention implements TableConventionInterface
{
    /**
     * @var Inflector
     */
    private $inflector;
    /**
     * @var ReaderInterface
     */
    private $annotationReader;

    public function __construct(Inflector $inflector, ReaderInterface $annotationReader)
    {
        $this->inflector = $inflector;
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param string|object $classOrObject
     * @return string The database table name
     */
    public function getTable($classOrObject)
    {
        $annotations = $this->annotationReader->getClassAnnotations($classOrObject);
        if(isset($annotations['table'])) {
            $table = $annotations['table'];
            if(is_string($table)) {
                return $table;
            }
        }

        $class = $this->inflector->getShortClass($classOrObject);
        return DoctrineInflector::tableize(DoctrineInflector::pluralize($class));
    }
}