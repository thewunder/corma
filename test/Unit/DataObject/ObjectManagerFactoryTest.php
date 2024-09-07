<?php

namespace Corma\Test\Unit\DataObject;

use Corma\DataObject\Factory\PsrContainerObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ObjectManagerFactoryTest extends TestCase
{
    private MySQLQueryHelper|MockObject $queryHelper;
    private ContainerInterface|MockObject $container;

    public function setUp(): void
    {
        parent::setUp();
        $this->queryHelper = $this->getMockBuilder(MySQLQueryHelper::class)->disableOriginalConstructor()->getMock();
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
    }

    public function testWithDefaults(): void
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, Inflector::build(), $this->container);
        $this->assertInstanceOf(PsrContainerObjectFactory::class, $omf->getFactory());
        $this->assertInstanceOf(DefaultTableConvention::class, $omf->getTableConvention());
        $this->assertInstanceOf(ClosureHydrator::class, $omf->getHydrator());
        $this->assertInstanceOf(CustomizableAutoIncrementIdentifier::class, $omf->getIdentifier());
    }

    public function testGetManager(): void
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, Inflector::build(), $this->container);
        $objectManager = $omf->getManager(ExtendedDataObject::class);
        $this->assertInstanceOf(ObjectManager::class, $objectManager);
    }
}
