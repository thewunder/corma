<?php
namespace Corma\Test;

use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\Relationship\RelationshipLoader;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Corma\Util\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ObjectMapperTest extends TestCase
{
    private Connection|MockObject $connection;
    private ContainerInterface|MockObject $container;

    public function setUp(): void
    {
        $this->connection = $this->mockConnection();
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
    }

    public function testCreate(): ObjectMapper
    {
        $corma = ObjectMapper::withDefaults($this->connection, $this->container);
        $this->assertInstanceOf(ObjectMapper::class, $corma);
        return $corma;
    }

    /**
     * @depends testCreate
     * @param ObjectMapper $corma
     */
    public function testGetRepository(ObjectMapper $corma)
    {
        $repository = $corma->getRepository(ExtendedDataObject::class);
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testCreateObject()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('create');

        $this->getCorma($mockRepo)->create(ExtendedDataObject::class);
    }

    public function testFind()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('find')->with(5);

        $this->getCorma($mockRepo)->find(ExtendedDataObject::class, 5);
    }

    public function testFindByIds()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findByIds')->with([5, 15]);

        $this->getCorma($mockRepo)->findByIds(ExtendedDataObject::class, [5, 15]);
    }

    public function testFindAll()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findAll')->willReturn([]);

        $this->getCorma($mockRepo)->findAll(ExtendedDataObject::class);
    }

    public function testFindBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findBy')->with(['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1)->willReturn([]);

        $this->getCorma($mockRepo)->findBy(ExtendedDataObject::class, ['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1);
    }

    public function testFindOneBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findOneBy')->with(['asdf'=>'value'], ['asdf'=>'ASC']);

        $this->getCorma($mockRepo)->findOneBy(ExtendedDataObject::class, ['asdf'=>'value'], ['asdf'=>'ASC']);
    }

    public function testLoadOneToMany()
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(123);
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(456);


        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm = $this->getCorma($mockRepo);
        /** @var MockObject $mockLoader */
        $mockLoader = $orm->getRelationshipLoader();

        $return = ['789' => new OtherDataObject()];
        $mockLoader->expects($this->once())->method('loadOne')
            ->with($objects, OtherDataObject::class, 'otherDataObjectId')
            ->willReturn($return);

        $loaded = $orm->loadOne($objects, OtherDataObject::class, 'otherDataObjectId');
        $this->assertEquals($return, $loaded);
    }

    public function testLoadManyToOne()
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(123);
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(456);


        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm = $this->getCorma($mockRepo);
        /** @var MockObject $mockLoader */
        $mockLoader = $orm->getRelationshipLoader();

        $return = ['789' => new OtherDataObject()];
        $mockLoader->expects($this->once())->method('loadMany')
            ->with($objects, OtherDataObject::class, 'extendedDataObjectId')
            ->willReturn($return);

        $loaded = $orm->loadMany($objects, OtherDataObject::class, 'extendedDataObjectId');
        $this->assertEquals($return, $loaded);
    }

    public function testLoadManyToMany()
    {
        $objects = [];
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(123);
        $object = new ExtendedDataObject();
        $objects[] = $object->setId(456);


        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orm = $this->getCorma($mockRepo);
        /** @var MockObject $mockLoader */
        $mockLoader = $orm->getRelationshipLoader();

        $return = ['789' => new OtherDataObject()];
        $mockLoader->expects($this->once())->method('loadManyToMany')
            ->with($objects, OtherDataObject::class, 'link_table')
            ->willReturn($return);

        $loaded = $orm->loadManyToMany($objects, OtherDataObject::class, 'link_table');
        $this->assertEquals($return, $loaded);
    }

    public function testSave()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object = new ExtendedDataObject();
        $mockRepo->expects($this->once())->method('save')->with($object);

        $this->getCorma($mockRepo)->save($object);
    }

    public function testSaveAll()
    {
        $objects = [];
        $objects[] = new ExtendedDataObject();
        $objects[] = new ExtendedDataObject();

        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('saveAll')->with($objects);

        $this->getCorma($mockRepo)->saveAll($objects);
    }

    public function testDelete()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object = new ExtendedDataObject();
        $mockRepo->expects($this->once())->method('delete')->with($object);

        $this->getCorma($mockRepo)->delete($object);
    }

    public function testDeleteAll()
    {
        $objects = [];
        $objects[] = new ExtendedDataObject();
        $objects[] = new ExtendedDataObject();

        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('deleteAll')->with($objects);

        $this->getCorma($mockRepo)->deleteAll($objects);
    }

    /**
     * @depends testCreate
     * @param ObjectMapper $corma
     */
    public function testGetQueryHelper(ObjectMapper $corma)
    {
        $this->assertInstanceOf(QueryHelper::class, $corma->getQueryHelper());
    }

    public function testUnitOfWork()
    {
        $corma = ObjectMapper::withDefaults($this->connection, $this->container);

        $unitOfWork = $corma->unitOfWork();
        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }

    protected function getCorma(MockObject $mockRepository): ObjectMapper
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();


        $repositoryFactory = $this->getMockBuilder(ObjectRepositoryFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryFactory->method('getRepository')->with(ExtendedDataObject::class)->willReturn($mockRepository);

        $objectManagerFactory = $this->getMockBuilder(ObjectManagerFactory::class)
            ->disableOriginalConstructor()->getMock();

        $loader = $this->getMockBuilder(RelationshipLoader::class)->disableOriginalConstructor()->getMock();

        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->setConstructorArgs([new QueryHelper($connection, new LimitedArrayCache()), $repositoryFactory, $objectManagerFactory,  Inflector::build()])
            ->onlyMethods(['getRelationshipLoader'])->getMock();

        $objectMapper->method('getRelationshipLoader')->willReturn($loader);

        return $objectMapper;
    }

    private function mockConnection(): Connection|MockObject
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySqlPlatform());
        return $connection;
    }
}
