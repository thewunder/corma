<?php
namespace Corma\Repository;

use Closure;

/**
 * Repository that aggressively caches its results so that find, findByIds, and findAll operate exclusively from cache.
 *
 * Not meant for large tables as the entire table is loaded into a single cache entry.
 *
 * Saving and deleting clears all data from the cache.
 */
abstract class AggressiveCachingObjectRepository extends ObjectRepository
{
    public function find($id, bool $useCache = true): ?object
    {
        if ($useCache) {
            $this->findAll();
        }

        return parent::find($id, $useCache);
    }

    public function findByIds(array $ids, bool $useCache = true): array
    {
        if ($useCache) {
            $this->findAll();
        }

        return parent::findByIds($ids);
    }

    public function findAll(): array
    {
        $key = $this->getCacheKey();
        if ($this->cache->has($key)) {
            return $this->restoreAllFromCache($key);
        }

        $objects = parent::findAll();

        $this->storeAllInCache($objects, $key);

        return $objects;
    }

    public function save(object $object, string|Closure|null ...$saveRelationships): object
    {
        parent::save($object, ...$saveRelationships);
        $this->cache->delete($this->getCacheKey());
        return $object;
    }

    public function saveAll(array $objects, string|Closure|null ...$saveRelationships): int
    {
        $result = parent::saveAll($objects, ...$saveRelationships);
        $this->cache->delete($this->getCacheKey());
        return $result;
    }

    public function delete(object $object): void
    {
        parent::delete($object);
        $this->cache->delete($this->getCacheKey());
    }

    public function deleteAll(array $objects): int
    {
        $result = parent::deleteAll($objects);
        $this->cache->delete($this->getCacheKey());
        return $result;
    }

    protected function getCacheKey(): string
    {
        return "all_{$this->getTableName()}";
    }
}
