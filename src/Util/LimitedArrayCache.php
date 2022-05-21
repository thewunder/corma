<?php

namespace Corma\Util;

use Doctrine\Common\Cache\CacheProvider;

/**
 * An Array backed cache that evicts based on a maximum size
 */
class LimitedArrayCache extends CacheProvider
{
    /**
     * Number of keys in this cache
     */
    const STATS_KEYS = 'keys';

    private const DEFAULT_LIMIT = 1000;

    private array $data = [];
    private array $expirations = [];
    private int $hits = 0;
    private int $misses = 0;
    private int $start;
    private int $limit;

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
        if ($this->doContains($id)) {
            $this->hits++;
            return $this->data[$id];
        }
        $this->misses++;
        return false;
    }

    /**
     * @inheritDoc
     */
    protected function doContains($id): bool
    {
        if (!isset($this->data[$id])) {
            return false;
        }

        $expiration = $this->expirations[$id] ?? false;

        if ($expiration && $expiration < time()) {
            $this->doDelete($id);

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doSave($id, $data, $lifeTime = 0): bool
    {
        $this->data[$id] = $data;

        if ($lifeTime > 0) {
            $this->expirations[$id] = time() + $lifeTime;
        }

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
            unset($this->data[$key], $this->expirations[$key]);
            $evicted++;
            if ($evicted >= $toEvict) {
                break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function doDelete($id): bool
    {
        unset($this->data[$id]);
        unset($this->expirations[$id]);
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function doFlush()
    {
        $this->data = [];
        $this->expirations = [];
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
