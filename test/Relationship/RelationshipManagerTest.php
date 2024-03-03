<?php

namespace Corma\Test\Relationship;

use Corma\ObjectMapper;
use Corma\Relationship\OneToOne;
use Corma\Relationship\OneToOneHandler;
use Corma\Relationship\RelationshipManager;
use Corma\Test\Fixtures\ExtendedDataObject;
use PHPUnit\Framework\TestCase;

class RelationshipManagerTest extends TestCase
{
    private RelationshipManager $reader;
    public function setUp(): void
    {
        $orm = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $this->reader = new RelationshipManager([
            new OneToOneHandler($orm)
        ]);
    }
    public function testReadAttribute()
    {
        $attribute = $this->reader->readAttribute(ExtendedDataObject::class, 'objectProperty');
        $this->assertInstanceOf(OneToOne::class, $attribute);
    }

    public function testGetHandler()
    {
        $handler = $this->reader->getHandler(ExtendedDataObject::class, 'objectProperty');
        $this->assertInstanceOf(OneToOneHandler::class, $handler);
    }
}
