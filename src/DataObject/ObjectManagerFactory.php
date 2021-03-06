<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Factory\PsrContainerObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\AutoIncrementIdentifier;
use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\AnnotationCustomizableTableConvention;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\DataObject\TableConvention\TableConventionInterface;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\Util\Inflector;
use Minime\Annotations\Interfaces\ReaderInterface;
use Psr\Container\ContainerInterface;

class ObjectManagerFactory
{
    /**
     * @var ObjectHydratorInterface
     */
    protected $hydrator;
    /**
     * @var ObjectIdentifierInterface
     */
    protected $identifier;
    /**
     * @var ObjectFactoryInterface
     */
    protected $factory;
    /**
     * @var TableConventionInterface
     */
    protected $tableConvention;

    public function __construct(ObjectHydratorInterface $hydrator, ObjectIdentifierInterface $identifier, TableConventionInterface $tableConvention, ObjectFactoryInterface $factory)
    {
        $this->hydrator = $hydrator;
        $this->identifier = $identifier;
        $this->tableConvention = $tableConvention;
        $this->factory = $factory;
    }

    /**
     * @param QueryHelperInterface $queryHelper
     * @param Inflector $inflector
     * @param ReaderInterface|null $reader
     * @param ContainerInterface|null $container
     * @return ObjectManagerFactory
     */
    public static function withDefaults(QueryHelperInterface $queryHelper, Inflector $inflector, ?ReaderInterface $reader = null, ?ContainerInterface $container = null): self
    {
        $hydrator = new ClosureHydrator();
        if ($container) {
            $factory = new PsrContainerObjectFactory($container, $hydrator);
        } else {
            $factory = new PdoObjectFactory($hydrator);
        }

        if ($reader) {
            $tableConvention = new AnnotationCustomizableTableConvention($inflector, $reader);
            $identifier = new CustomizableAutoIncrementIdentifier($inflector, $reader, $queryHelper, $tableConvention);
        } else {
            $tableConvention = new DefaultTableConvention($inflector);
            $identifier = new AutoIncrementIdentifier($inflector, $queryHelper, $tableConvention);
        }

        return new static($hydrator, $identifier, $tableConvention, $factory);
    }

    /**
     * Populates object handling classes with defaults if not provided
     *
     * @param string $className
     * @param array $dependencies
     * @param ObjectHydratorInterface|null $hydrator Setting a custom object hydrator can change how columns are mapped to property names and how those properties are set
     * @param ObjectIdentifierInterface|null $identifier Setting a custom object identifier can change how your id is generated, retrieved, and set
     * @param TableConventionInterface|null $tableConvention If you have enabled annotations (by constructing Corma with an annotation reader), you should use @table instead of injecting a custom convention here.
     * @param ObjectFactoryInterface|null $factory Setting a custom object factory will customize how your object is instantiated
     *
     * @return ObjectManager
     */
    public function getManager(string $className, array $dependencies = [], ?ObjectHydratorInterface $hydrator = null, ?ObjectIdentifierInterface $identifier = null, ?TableConventionInterface $tableConvention = null, ?ObjectFactoryInterface $factory = null)
    {
        $hydrator = $hydrator ? $hydrator : clone $this->hydrator;
        $identifier = $identifier ? $identifier : clone $this->identifier;
        $tableConvention = $tableConvention ? $tableConvention : clone $this->tableConvention;
        $factory = $factory ? $factory : clone $this->factory;

        return new ObjectManager($hydrator, $identifier, $tableConvention, $factory, $className, $dependencies);
    }

    /**
     * @return ObjectFactoryInterface
     */
    public function getFactory(): ObjectFactoryInterface
    {
        return $this->factory;
    }

    /**
     * @return ObjectHydratorInterface
     */
    public function getHydrator(): ObjectHydratorInterface
    {
        return $this->hydrator;
    }

    /**
     * @return TableConventionInterface
     */
    public function getTableConvention(): TableConventionInterface
    {
        return $this->tableConvention;
    }

    /**
     * @return ObjectIdentifierInterface
     */
    public function getIdentifier(): ObjectIdentifierInterface
    {
        return $this->identifier;
    }
}
