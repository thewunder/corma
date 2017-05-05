<?php
namespace Corma\DataObject\TableConvention;

use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

/**
 * Allows for a customizable database table via the "@table" annotation
 */
class AnnotationCustomizableTableConvention extends DefaultTableConvention
{
    /**
     * @var ReaderInterface
     */
    protected $annotationReader;

    public function __construct(Inflector $inflector, ReaderInterface $annotationReader)
    {
        parent::__construct($inflector);
        $this->annotationReader = $annotationReader;
    }

    /**
     * @param string|object $classOrObject
     * @return string The database table name
     */
    public function getTable($classOrObject): string
    {
        $annotations = $this->annotationReader->getClassAnnotations($classOrObject);
        if (isset($annotations['table'])) {
            $table = $annotations['table'];
            if (is_string($table)) {
                return $table;
            }
        }

        return parent::getTable($classOrObject);
    }
}
