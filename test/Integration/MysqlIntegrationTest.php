<?php
namespace Corma\Test\Integration;

use Corma\DataObject\ObjectManagerFactory;
use Corma\DBAL\ConnectionException;
use Corma\ObjectMapper;
use Corma\QueryHelper\MySQLQueryHelper;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Util\Inflector;
use Corma\Util\LimitedArrayCache;
use Corma\DBAL\DriverManager;
use Corma\DBAL\Exception;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

class MysqlIntegrationTest extends BaseIntegrationCase
{
    public function testIsDuplicateException(): void
    {
        $cache = new LimitedArrayCache();
        $mySQLQueryHelper = new MySQLQueryHelper(self::$platform->getConnection(), $cache);
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $objectManagerFactory = ObjectManagerFactory::withDefaults($mySQLQueryHelper, Inflector::build(), $container);
        $objectMapper = $this->getMockBuilder(ObjectMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $objectMapper->method('getObjectManagerFactory')->willReturn($objectManagerFactory);
        $this->repository = new ExtendedDataObjectRepository(self::$platform->getConnection(), $objectMapper, $cache, $this->dispatcher);

        $this->assertFalse($mySQLQueryHelper->isDuplicateException(new ConnectionException()));

        try {
            $this->repository->causeUniqueConstraintViolation();
        } catch (Exception $e) {
            $this->assertTrue($mySQLQueryHelper->isDuplicateException($e));
            return;
        }

        $this->markTestIncomplete('Expected Exception was not thrown');
    }

    public function testUpsertWithoutPrimaryKey(): void
    {
        $object = new ExtendedDataObject();
        $object->setMyColumn('Upsert EDO');
        $this->objectMapper->save($object);

        $otherObject = new OtherDataObject();
        $otherObject->setName('Upsert ODO');
        $this->objectMapper->save($otherObject);

        $return = $this->objectMapper->getQueryHelper()
            ->massUpsert('extended_other_rel', [['extendedDataObjectId'=>$object->getId(), 'otherDataObjectId'=>$otherObject->getId()]]);

        $this->assertEquals(1, $return);
    }
}
