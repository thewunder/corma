<?php
namespace Corma\Test\Unit\Repository;

use Corma\Exception\BadMethodCallException;
use Corma\ObjectMapper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\Repository\ReadOnlyRepository;
use Corma\Util\LimitedArrayCache;
use Corma\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReadOnlyRepositoryTest extends TestCase
{
    protected ObjectMapper|MockObject $objectMapper;
    private Connection|MockObject $connection;
    private LimitedArrayCache|MockObject $cache;

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

    public function testSave(): void
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->save($object);
    }

    public function testSaveAll(): void
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->saveAll([$object]);
    }

    public function testDelete(): void
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->delete($object);
    }

    public function testDeleteAll(): void
    {
        $this->expectException(BadMethodCallException::class);
        $object = new ExtendedDataObject();
        $repo = $this->getRepository();
        $repo->deleteAll([$object]);
    }

    protected function getRepository(): ReadOnlyRepository|MockObject
    {
        return $this->getMockBuilder(ReadOnlyRepository::class)
            ->setConstructorArgs([$this->connection, $this->objectMapper, $this->cache])
            ->onlyMethods(['fetchAll'])->getMock();
    }
}
