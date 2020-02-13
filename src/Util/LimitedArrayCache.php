<?php

namespace Corma\Util;

use Doctrine\Common\Cache\CacheProvider;

/**
 * An Array backed cache that evicts based on a maximum size rather than expiration
 */
class LimitedArrayCache extends CacheProvider
{
    /**
     * Number of keys in this cache
     */
    const STATS_KEYS = 'keys';

    private const DEFAULT_LIMIT = 1000;

    /** @var array  */
    private $data = [];

    /** @var int  */
    private $hits = 0;

    /** @var int  */
    private $misses = 0;

    /** @var int */
    private $start;

    /** @var int */
    private $limit;

    public function __construct(int $limit = self::DEFAULT_LIMIT)
    {
        $this->start = time();
        $this->limit = $limit;
    }

    /**
     * @inheritDoc
     */
    protected function doFetch($id)
    {
        if (isset($this->data[$id])) {
            $this->hits++;
            return $this->data[$id];
        }
        $this->misses++;
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function doContains($id)
    {
        return isset($this->data[$id]);
    }

    /**
     * @param string $id
     * @param mixed $data
     * @param int $lifeTime Ignored
     * @return bool|void
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $this->data[$id] = $data;

        if (count($this->data) > $this->limit) {
            $this->evictKeys();
        }

        return true;
    }

    /**
     * Evict half the cache keys in a FIFO manner
     */
    private function evictKeys()
    {
        $evicted = 0;
        $toEvict = $this->limit / 2;
        foreach ($this->data as $key => $value) {
            unset($this->data[$key]);
            $evicted++;
            if ($evicted >= $toEvict) {
                break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function doDelete($id)
    {
        unset($this->data[$id]);
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doFlush()
    {
        $this->data = [];
    }

    /**
     * @inheritDoc
     */
    protected function doGetStats()
    {
        return [
            self::STATS_HITS => $this->hits,
            self::STATS_MISSES => $this->misses,
            self::STATS_UPTIME => $this->start - time(),
            self::STATS_MEMORY_AVAILABLE => null,
            self::STATS_MEMORY_USAGE => null,
            self::STATS_KEYS  => count($this->data)
        ];
    }
}
