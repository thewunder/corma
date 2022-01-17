<?php

namespace Corma\Test\DataObject;

use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Factory\PsrContainerObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Identifier\AutoIncrementIdentifier;
use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\DataObject\TableConvention\CustomizableTableConvention;
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

    public function setUp(): void
    {
        parent::setUp();
        $this->queryHelper = $this->getMockBuilder(MySQLQueryHelper::class)->disableOriginalConstructor()->getMock();
    }

    public function testWithDefaults()
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, Inflector::build());
        $this->assertInstanceOf(PdoObjectFactory::class, $omf->getFactory());
        $this->assertInstanceOf(DefaultTableConvention::class, $omf->getTableConvention());
        $this->assertInstanceOf(ClosureHydrator::class, $omf->getHydrator());
        $this->assertInstanceOf(CustomizableAutoIncrementIdentifier::class, $omf->getIdentifier());
    }

    public function testWithDefaultsWithContainer()
    {
        /** @var MockObject|ContainerInterface $container */
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, Inflector::build(), $container);
        $this->assertInstanceOf(PsrContainerObjectFactory::class, $omf->getFactory());
        $this->assertInstanceOf(DefaultTableConvention::class, $omf->getTableConvention());
        $this->assertInstanceOf(ClosureHydrator::class, $omf->getHydrator());
        $this->assertInstanceOf(CustomizableAutoIncrementIdentifier::class, $omf->getIdentifier());
    }

    public function testGetManager()
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, Inflector::build());
        $objectManager = $omf->getManager(ExtendedDataObject::class);
        $this->assertInstanceOf(ObjectManager::class, $objectManager);
    }
}
