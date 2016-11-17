<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\AutoIncrementIdentifier;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\AnnotationCustomizableTableConvention;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\DataObject\TableConvention\TableConventionInterface;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;

class ObjectManagerFactory
{
    /**
     * @var ObjectFactoryInterface
     */
    protected $factory;
    /**
     * @var ObjectHydratorInterface
     */
    protected $hydrator;
    /**
     * @var TableConventionInterface
     */
    protected $tableConvention;
    /**
     * @var ObjectIdentifierInterface
     */
    protected $identifier;

    public function __construct(ObjectFactoryInterface $factory, ObjectHydratorInterface $hydrator, TableConventionInterface $tableConvention, ObjectIdentifierInterface $identifier)
    {
        $this->factory = $factory;
        $this->hydrator = $hydrator;
        $this->tableConvention = $tableConvention;
        $this->identifier = $identifier;
    }

    /**
     * @param QueryHelperInterface $queryHelper
     * @param Inflector $inflector
     * @param ReaderInterface|null $reader
     * @return ObjectManagerFactory
     */
    public static function withDefaults(QueryHelperInterface $queryHelper, Inflector $inflector, ReaderInterface $reader = null)
    {
        $hydrator = new ClosureHydrator();
        $factory = new PdoObjectFactory($hydrator);
        if($reader) {
            $tableConvention = new AnnotationCustomizableTableConvention($inflector, $reader);
        } else {
            $tableConvention = new DefaultTableConvention($inflector);
        }

        $identifier = new AutoIncrementIdentifier($inflector, $reader, $queryHelper, $tableConvention);
        return new static($factory, $hydrator, $tableConvention, $identifier);
    }

    /**
     * Populates object handling classes with defaults if not provided
     *
     * @param $className
     * @param array $dependencies
     * @param ObjectFactoryInterface|null $factory
     * @param ObjectHydratorInterface|null $hydrator
     * @param TableConventionInterface|null $tableConvention
     * @param ObjectIdentifierInterface|null $identifier
     * @return ObjectManager
     */
    public function getManager($className, $dependencies = [], ObjectFactoryInterface $factory = null, ObjectHydratorInterface $hydrator = null, TableConventionInterface $tableConvention = null, ObjectIdentifierInterface $identifier = null)
    {
        $factory = $factory ? $factory : $this->factory;
        $hydrator = $hydrator ? $hydrator : $this->hydrator;
        $tableConvention = $tableConvention ? $tableConvention : $this->tableConvention;
        $identifier = $identifier ? $identifier : $this->identifier;

        return new ObjectManager($factory, $hydrator, $tableConvention, $identifier, $className, $dependencies);
    }
}