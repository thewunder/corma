<?php
namespace Corma\Test;

use Corma\Corma;
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

    /**
     * @depends testCreate
     * @param Corma $corma
     */
    public function testGetQueryHelper(Corma $corma)
    {
        $this->assertInstanceOf(QueryHelper::class, $corma->getQueryHelper());
    }
}
