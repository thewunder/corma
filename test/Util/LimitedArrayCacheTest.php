<?php

namespace Corma\Test\Util;

use Corma\Util\LimitedArrayCache;
use PHPUnit\Framework\TestCase;

class LimitedArrayCacheTest extends TestCase
{
    public function testSaveAndFetch()
    {
        $cache = new LimitedArrayCache();
        $cache->save('test_key', 'value');
        $this->assertEquals('value', $cache->fetch('test_key'));
    }

    public function testDeleteAndContains()
    {
        $cache = new LimitedArrayCache();
        $cache->save('test_key', 'value');
        $cache->delete('test_key');
        $this->assertFalse($cache->contains('test_key'));
    }

    public function testSaveMultipleAndFetchMultiple()
    {
        $cache = new LimitedArrayCache();
        $cache->saveMultiple(['key1'=>'value1', 'key2'=>'value2']);
        $values = $cache->fetchMultiple(['key2', 'key1']);
        $this->assertEquals(['key2'=>'value2', 'key1'=>'value1'], $values);
    }

    public function testEviction()
    {
        $cache = new LimitedArrayCache(11);
        $data = [];
        for($i = 1; $i < 12; $i++) {
            $data['key'.$i] = 'value'.$i;
        }
        $cache->saveMultiple($data);
        $this->assertEquals(11, $cache->getStats()[$cache::STATS_KEYS]);
        $cache->save('key12', 'value12');
        $this->assertEquals(6, $cache->getStats()[$cache::STATS_KEYS]);
        $this->assertFalse($cache->contains('key6'));
        $this->assertTrue($cache->contains('key7'));
    }

    public function testExpiration()
    {
        $cache = new LimitedArrayCache();
        $cache->save('test_key', 'value', 0.5);
        $this->assertTrue($cache->contains('test_key'));
        sleep(1);
        $this->assertFalse($cache->contains('test_key'));
    }

    public function testFlushAll()
    {
        $cache = new LimitedArrayCache();
        $cache->save('test_key', 'value');
        $cache->flushAll();
        $this->assertFalse($cache->contains('test_key'));
        $this->assertEquals(0, $cache->getStats()[$cache::STATS_KEYS]);
    }
}
