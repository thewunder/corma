<?php

namespace Corma\Test\Relationship;

use Corma\Relationship\OneToOne;
use Corma\Relationship\OneToOneHandler;
use Corma\Relationship\RelationshipReader;
use Corma\Test\Fixtures\ExtendedDataObject;
use PHPUnit\Framework\TestCase;

class RelationshipReaderTest extends TestCase
{
    private RelationshipReader $reader;
    public function setUp(): void
    {
        $this->reader = new RelationshipReader([
            new OneToOneHandler()
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
