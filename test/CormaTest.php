<?php
namespace Corma\Test;

use Corma\Corma;
use Corma\Repository\ObjectRepositoryFactory;
use Corma\Repository\ObjectRepositoryFactoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\QueryHelper;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CormaTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $dispatcher = new EventDispatcher();
        $cache = new ArrayCache();

        $corma = Corma::create($connection, $dispatcher, $cache, ['Corma\\Test\\Fixtures']);
        $this->assertInstanceOf(Corma::class, $corma);
        return $corma;
    }

    /**
     * @depends testCreate
     * @param Corma $corma
     */
    public function testGetRepository(Corma $corma)
    {
        $repository = $corma->getRepository('ExtendedDataObject');
        $this->assertInstanceOf(ExtendedDataObjectRepository::class, $repository);
    }

    public function testFind()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('find')->with(5);

        $this->getCorma($mockRepo)->find('ExtendedDataObject', 5);
    }

    public function testFindAll()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findAll');

        $this->getCorma($mockRepo)->findAll('ExtendedDataObject');
    }

    public function testFindBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findBy')->with(['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1);

        $this->getCorma($mockRepo)->findBy('ExtendedDataObject', ['asdf'=>'value'], ['asdf'=>'ASC'], 2, 1);
    }

    public function testFindOneBy()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockRepo->expects($this->once())->method('findOneBy')->with(['asdf'=>'value']);

        $this->getCorma($mockRepo)->findOneBy('ExtendedDataObject', ['asdf'=>'value']);
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

    public function testDelete()
    {
        $mockRepo = $this->getMockBuilder(ExtendedDataObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $object = new ExtendedDataObject();
        $mockRepo->expects($this->once())->method('delete')->with($object);

        $this->getCorma($mockRepo)->delete($object);
    }

    /**
     * @depends testCreate
     * @param Corma $corma
     */
    public function testGetQueryHelper(Corma $corma)
    {
        $this->assertInstanceOf(QueryHelper::class, $corma->getQueryHelper());
    }

    /**
     * @param $mockRepository
     * @return Corma
     */
    protected function getCorma(\PHPUnit_Framework_MockObject_MockObject $mockRepository)
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();


        $mockFactory = $this->getMockBuilder(ObjectRepositoryFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockFactory->expects($this->once())->method('getRepository')->with('ExtendedDataObject')->willReturn($mockRepository);

        /** @var ObjectRepositoryFactoryInterface $mockFactory */
        return new Corma(new QueryHelper($connection, new ArrayCache()), $mockFactory);
    }
}
