<?php
namespace Corma\Test\Repository;

use Corma\Exception\BadMethodCallException;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ReadOnlyRepository;
use Corma\Util\LimitedArrayCache;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReadOnlyRepositoryTest extends TestCase
{
    /** @var MockObject */
    protected $objectMapper;

    /** @var MockObject */
    private $connection;

    /** @var MockObject */
    private $cache;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->cache = $this->getMockBuilder(LimitedArrayCache::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testSave()
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveAll()
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->saveAll([$object]);
    }

    public function testDelete()
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testDeleteAll()
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->deleteAll([$object]);
    }

    /**
     * @return ReadOnlyRepository|MockObject
     */
    protected function getRepository()
    {
        return $this->getMockBuilder(ReadOnlyRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->onlyMethods(['fetchAll'])->getMock();
    }
}
