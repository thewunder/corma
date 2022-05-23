<?php

namespace Corma\Test\Util;

use Corma\Util\LimitedArrayCache;
use PHPUnit\Framework\TestCase;

class LimitedArrayCacheTest extends TestCase
{
    public function testSetAndGet()
    {
        $cache = new LimitedArrayCache();
        $cache->set('test_key', 'value');
        $this->assertEquals('value', $cache->get('test_key'));
    }

    public function testDeleteAndContains()
    {
        $cache = new LimitedArrayCache();
        $cache->set('test_key', 'value');
        $cache->delete('test_key');
        $this->assertFalse($cache->has('test_key'));
    }

    public function testSetMultipleAndFetchMultiple()
    {
        $cache = new LimitedArrayCache();
        $cache->setMultiple(['key1'=>'value1', 'key2'=>'value2']);
        $values = $cache->getMultiple(['key2', 'key1']);
        $this->assertEquals(['key2'=>'value2', 'key1'=>'value1'], $values);
    }

    public function testEviction()
    {
        $cache = new LimitedArrayCache(11);
        $data = [];
        for($i = 1; $i < 12; $i++) {
            $data['key'.$i] = 'value'.$i;
        }
        $cache->setMultiple($data);
        $this->assertEquals(11, $cache->count());
        $cache->set('key12', 'value12');
        $this->assertEquals(6, $cache->count());
        $this->assertFalse($cache->has('key6'));
        $this->assertTrue($cache->has('key7'));
    }

    public function testExpiration()
    {
        $cache = new LimitedArrayCache();
        $cache->set('test_key', 'value', 1);
        $this->assertTrue($cache->has('test_key'));
        sleep(2);
        $this->assertFalse($cache->has('test_key'));
    }

    public function testClear()
    {
        $cache = new LimitedArrayCache();
        $cache->set('test_key', 'value');
        $cache->clear();
        $this->assertFalse($cache->has('test_key'));
        $this->assertEquals(0, $cache->count());
    }
}
