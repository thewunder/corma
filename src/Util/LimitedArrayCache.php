<?php

namespace Corma\Util;

use Psr\SimpleCache\CacheInterface;

/**
 * An Array backed cache that evicts based on a maximum size
 */
class LimitedArrayCache implements CacheInterface
{
    private const DEFAULT_LIMIT = 1000;

    private array $data = [];
    private array $expirations = [];

    public function __construct(private int $limit = self::DEFAULT_LIMIT)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }
        return $default;
    }

    public function has(string $key): bool
    {
        if (!isset($this->data[$key])) {
            return false;
        }

        $expiration = $this->expirations[$key] ?? false;

        if ($expiration && $expiration < time()) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->data[$key] = $value;

        if ($ttl instanceof \DateInterval) {
            $ttl = date_create('@0')->add($ttl)->getTimestamp();
        }

        if ($ttl > 0) {
            $this->expirations[$key] = time() + $ttl;
        }

        if (count($this->data) > $this->limit) {
            $this->evictKeys();
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value != null) {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key]);
        unset($this->expirations[$key]);
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
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

    public function clear(): bool
    {
        $this->data = [];
        $this->expirations = [];
        return true;
    }

    /**
     * @return int Number of keys currently in cache
     */
    public function count(): int
    {
        return count($this->data);
    }
}
