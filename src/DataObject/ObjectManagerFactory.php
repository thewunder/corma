<?php
namespace Corma\DataObject;

use Corma\DataObject\Factory\ObjectFactoryInterface;
use Corma\DataObject\Hydrator\ObjectHydratorInterface;
use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\DataObject\TableConvention\TableConventionInterface;

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