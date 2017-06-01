<?php

namespace DataObject;

use Corma\DataObject\Factory\PdoObjectFactory;
use Corma\DataObject\Hydrator\ClosureHydrator;
use Corma\DataObject\Identifier\AutoIncrementIdentifier;
use Corma\DataObject\Identifier\CustomizableAutoIncrementIdentifier;
use Corma\DataObject\ObjectManager;
use Corma\DataObject\ObjectManagerFactory;
use Corma\DataObject\TableConvention\AnnotationCustomizableTableConvention;
use Corma\DataObject\TableConvention\DefaultTableConvention;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Util\Inflector;
use Minime\Annotations\Reader;

class ObjectManagerFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $queryHelper;
    private $annotationReader;

    public function setUp()
    {
        parent::setUp();
        $this->queryHelper = $this->getMockBuilder(MySQLQueryHelper::class)->disableOriginalConstructor()->getMock();
        $this->annotationReader = $this->getMockBuilder(Reader::class)->disableOriginalConstructor()->getMock();
    }

    public function testWithDefaults()
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, new Inflector());
        $this->assertInstanceOf(PdoObjectFactory::class, $omf->getFactory());
        $this->assertInstanceOf(DefaultTableConvention::class, $omf->getTableConvention());
        $this->assertInstanceOf(ClosureHydrator::class, $omf->getHydrator());
        $this->assertInstanceOf(AutoIncrementIdentifier::class, $omf->getIdentifier());
    }

    public function testWithDefaultsWithReader()
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, new Inflector(), $this->annotationReader);
        $this->assertInstanceOf(PdoObjectFactory::class, $omf->getFactory());
        $this->assertInstanceOf(AnnotationCustomizableTableConvention::class, $omf->getTableConvention());
        $this->assertInstanceOf(ClosureHydrator::class, $omf->getHydrator());
        $this->assertInstanceOf(CustomizableAutoIncrementIdentifier::class, $omf->getIdentifier());
    }

    public function testGetManager()
    {
        $omf = ObjectManagerFactory::withDefaults($this->queryHelper, new Inflector());
        $objectManager = $omf->getManager(ExtendedDataObject::class);
        $this->assertInstanceOf(ObjectManager::class, $objectManager);
    }
}
