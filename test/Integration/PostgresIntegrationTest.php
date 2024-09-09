<?php
namespace Corma\Test\Integration;

use Corma\DataObject\ObjectManagerFactory;
use Corma\DBAL\ConnectionException;
use Corma\Exception\MissingPrimaryKeyException;
use Corma\ObjectMapper;
use Corma\QueryHelper\PostgreSQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Corma\DBAL\DriverManager;
use Corma\DBAL\Exception;
use Dotenv\Dotenv;

class PostgresIntegrationTest extends BaseIntegrationCase
{
    public function testIsDuplicateException(): void
    {
        $cache = new LimitedArrayCache();
        $mySQLQueryHelper = new PostgreSQLQueryHelper(self::$platform->getConnection(), $cache);

        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, Inflector::build(), $this->container);
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $objectMapper->expects($this->any())->method('getQueryHelper')->willReturn($mySQLQueryHelper);
        $objectMapper->expects($this->any())->method('getIdentityMap')->willReturn(new LimitedArrayCache());
        $this->repository = new ExtendedDataObjectRepository(self::$platform->getConnection(), $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new ConnectionException()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (Exception $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            $this->repository->deleteAll($this->repository->findAll());
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }


    public function testUpsertWithoutPrimaryKey(): void
    {
        $this->expectException(MissingPrimaryKeyException::class);

        $object = new ExtendedDataObject();
        $object->setMyColumn('Upsert EDO');
        $this->objectMapper->save($object);

        $otherObject = new OtherDataObject();
        $otherObject->setName('Upsert ODO');
        $this->objectMapper->save($otherObject);

        $this->objectMapper->getQueryHelper()
            ->massUpsert('extended_other_rel', [['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()]]);
    }
}
