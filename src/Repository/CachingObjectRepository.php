<?php
namespace Corma\Repository;

/**
 * Caches individual objects by id, by default for 24 hours
 */
abstract class CachingObjectRepository extends ObjectRepository
{
    public function find($id, bool $useCache = true): ?object
    {
        if (!$useCache) {
            return parent::find($id, false);
        }

        if (!$this->getIdentityMap()->contains($id)) {
            $dataFromCache = $this->cache->fetch($this->getCacheKey($id));
            if ($dataFromCache) {
                return $this->restoreFromCache($dataFromCache);
            }
        }
        $object = parent::find($id, $useCache);
        if ($object) {
            $this->storeInCache($object);
        }
        return $object;
    }

    public function findByIds(array $ids, bool $useCache = true): array
    {
        if (!$useCache) {
            return parent::findByIds($ids, false);
        }

        $om = $this->getObjectManager();
        $objects = $this->getIdentityMap()->fetchMultiple($ids);
        $cachedIds = [];
        foreach ($objects as $object) {
            $cachedIds[] = $om->getId($object);
        }
        $ids = array_diff($ids, $cachedIds);

        if (empty($ids)) {
            return $objects;
        }

        $keys = array_map(function ($id) {
            return $this->getCacheKey($id);
        }, $ids);

        $cachedData = $this->cache->fetchMultiple($keys);

        foreach ($cachedData as $data) {
            $object = $this->restoreFromCache($data);
            $objects[] = $object;
            unset($ids[array_search($om->getId($object), $ids)]);
        }

        if (empty($ids)) {
            return $objects;
        }

        $fromDb = parent::findByIds($ids, false);
        $this->storeMultipleInCache($fromDb);
        return array_merge($objects, $fromDb);
    }

    public function save(object $object, ?\Closure $saveRelationships = null): object
    {
        if (func_num_args() == 2) {
            $object = parent::save($object, $saveRelationships);
        } else {
            $object = parent::save($object);
        }
        $this->storeInCache($object);
        return $object;
    }

    public function saveAll(array $objects, ?\Closure $saveRelationships = null): int
    {
        if (func_num_args() == 2) {
            $result = parent::saveAll($objects, $saveRelationships);
        } else {
            $result = parent::saveAll($objects);
        }
        $this->storeMultipleInCache($objects);
        return $result;
    }

    public function delete($object): void
    {
        parent::delete($object);
        $om = $this->getObjectManager();
        $this->cache->delete($this->getCacheKey($om->getId($object)));
    }

    public function deleteAll(array $objects): int
    {
        $result = parent::deleteAll($objects);
        $om = $this->getObjectManager();
        /** @var object $object */
        foreach ($objects as $object) {
            $this->cache->delete($this->getCacheKey($om->getId($object)));
        }
        return $result;
    }

    /**
     * Return the cache lifetime in seconds
     *
     * @return int
     */
    protected function getCacheLifetime()
    {
        return 86400;
    }

    protected function storeInCache(object $object)
    {
        $om = $this->getObjectManager();
        $id = $om->getId($object);
        $this->cache->save($this->getCacheKey($id), $om->extract($object), $this->getCacheLifetime());
    }

    /**
     * @param object[] $objects
     */
    protected function storeMultipleInCache(array $objects)
    {
        $data = [];
        $om = $this->getObjectManager();
        foreach ($objects as $object) {
            $id = $om->getId($object);
            $data[$this->getCacheKey($id)] = $om->extract($object);
        }
        $this->cache->saveMultiple($data, $this->getCacheLifetime());
    }

    protected function getCacheKey(string $id): string
    {
        return $this->getTableName() . "[$id]";
    }
}
