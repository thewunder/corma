<?php
namespace Corma\Test;

use Corma\DataObject\ObjectManagerFactory;
use Corma\ObjectMapper;
use Corma\Relationship\RelationshipLoader;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\QueryHelper\QueryHelper;
use Corma\Util\Inflector;
use Corma\Util\UnitOfWork;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySqlPlatform;

class ObjectMapperTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySqlPlatform());

        $corma = ObjectMapper::withDefaults($connection, ['Corma\\Test\\Fixtures']);
        $this->assertInstanceOf(ObjectMapper::class, $corma);
        return $corma;
    }

    /**
     * @depends testCreate
     * @param ObjectMapper $corma
     */
    public function testGetRepository(ObjectMapper $corma)
    {
        $repository = $corma->getRepository('ExtendedDataObject');
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

        $loader = $this->getMockBuilder(RelationshipLoader::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findAll')->willReturn(new ArrayCollection([], $loader));

        $this->getCorma($mockRepo)->findAll(ExtendedDataObject::class);
    }

    public function testFindBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findBy')->with(['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1);

        $this->getCorma($mockRepo)->findBy(ExtendedDataObject::class, ['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1);
    }

    public function testFindOneBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findOneBy')->with(['asdf'=>'value']);

        $this->getCorma($mockRepo)->findOneBy(ExtendedDataObject::class, ['asdf'=>'value']);
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

        $return = ['789' => new OtherDataObject()];
        $mockRepo->expects($this->once())->method('loadOne')
            ->with($objects, OtherDataObject::class, 'otherDataObjectId')
            ->willReturn($return);

        $loaded = $this->getCorma($mockRepo)->loadOne($objects, OtherDataObject::class, 'otherDataObjectId');
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

        $return = ['789' => new OtherDataObject()];
        $mockRepo->expects($this->once())->method('loadMany')
            ->with($objects, OtherDataObject::class, 'extendedDataObjectId')
            ->willReturn($return);

        $loaded = $this->getCorma($mockRepo)->loadMany($objects, OtherDataObject::class, 'extendedDataObjectId');
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

        $return = ['789' => new OtherDataObject()];
        $mockRepo->expects($this->once())->method('loadManyToMany')
            ->with($objects, OtherDataObject::class, 'link_table')
            ->willReturn($return);

        $loaded = $this->getCorma($mockRepo)->loadManyToMany($objects, OtherDataObject::class, 'link_table');
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
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->any())->method('getDatabasePlatform')->willReturn(new MySqlPlatform());

        $corma = ObjectMapper::withDefaults($connection, ['Corma\\Test\\Fixtures']);

        $unitOfWork = $corma->unitOfWork();
        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }

    /**
     * @param $mockRepository
     * @return ObjectMapper
     */
    protected function getCorma(\PHPUnit_Framework_MockObject_MockObject $mockRepository)
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();


        $repositoryFactory = $this->getMockBuilder(ObjectRepositoryFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repositoryFactory->expects($this->once())->method('getRepository')->with(ExtendedDataObject::class)->willReturn($mockRepository);

        $objectManagerFactory = $this->getMockBuilder(ObjectManagerFactory::class)
            ->disableOriginalConstructor()->getMock();

        /** @var ObjectRepositoryFactoryInterface $repositoryFactory */
        return new ObjectMapper(new QueryHelper($connection, new ArrayCache()), $repositoryFactory, $objectManagerFactory,  new Inflector());
    }
}
