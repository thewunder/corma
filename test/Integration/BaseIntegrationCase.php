<?php
namespace Corma\Test\Integration;

use Corma\DataObject\Identifier\ObjectIdentifierInterface;
use Corma\ObjectMapper;
use Corma\Repository\ObjectRepositoryInterface;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Test\Fixtures\Repository\ExtendedDataObjectRepository;
use Corma\Test\Integration\Platform\DatabaseTestPlatform;
use Corma\Util\OffsetPagedQuery;
use Corma\Util\SeekPagedQuery;
use Corma\DBAL\Connection;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class BaseIntegrationCase extends TestCase
{
    protected ObjectRepositoryInterface $repository;
    protected EventDispatcher $dispatcher;
    protected ObjectMapper $objectMapper;
    protected ObjectIdentifierInterface $identifier;
    protected ContainerInterface|MockObject $container;

    protected static DatabaseTestPlatform $platform;

    public function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->container = $this->getMockBuilder(ContainerInterface::class)->getMock();
        $this->container->method('get')->willReturnCallback(fn(string $className) => new $className());
        $this->objectMapper = ObjectMapper::withDefaults(self::$platform->getConnection(), $this->container);
        $this->identifier = $this->objectMapper->getObjectManagerFactory()->getIdentifier();
        $this->repository = $this->objectMapper->getRepository(ExtendedDataObject::class);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$platform = static::getTestPlatform();
        self::$platform->createDatabase();
    }

    private static function getTestPlatform(): DatabaseTestPlatform
    {
        $dbPlatform = getenv('DB_PLATFORM');
        if (empty($dbPlatform) && file_exists(__DIR__.'/../../.env')) {
            $dotenv = new Dotenv(__DIR__.'/../../');
            $dotenv->load();
            $dbPlatform = getenv('DB_PLATFORM');
        }

        if (empty($dbPlatform)) {
            throw new \RuntimeException('DB_PLATFORM environment variable is not defined.');
        }

        $className = "Corma\\Test\\Integration\\Platform\\{$dbPlatform}TestPlatform";
        if(!class_exists($className)) {
            throw new \RuntimeException("Platform class not found: $className");
        }
        return $className::getInstance();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$platform->dropDatabase();
    }
}
