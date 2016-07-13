<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;

/**
 * Repository that aggressively caches its results so that find, findByIds, and findAll operate exclusively from cache.
 *
 * Not meant for large tables as the entire table is loaded into a single cache entry.
 *
 * Saving and deleting clears all data from the cache.
 */
abstract class AggressiveCachingObjectRepository extends ObjectRepository
{
    public function find($id, $useCache = true)
    {
        if ($useCache && empty($this->objectByIdCache)) {
            $this->findAll();
        }

        return parent::find($id, $useCache);
    }

    public function findByIds(array $ids, $useCache = true)
    {
        if ($useCache && empty($this->objectByIdCache)) {
            $this->findAll();
        }

        return parent::findByIds($ids);
    }

    public function findAll()
    {
        $key = $this->getCacheKey();
        if ($this->cache->contains($key)) {
            return $this->restoreAllFromCache($key);
        }

        $objects = parent::findAll();

        $this->storeAllInCache($objects, $key);

        return $objects;
    }

    public function save(DataObjectInterface $object)
    {
        parent::save($object);
        $this->cache->delete($this->getCacheKey());
        return $object;
    }

    public function saveAll(array $objects)
    {
        $result = parent::saveAll($objects);
        $this->cache->delete($this->getCacheKey());
        return $result;
    }

    public function delete(DataObjectInterface $object)
    {
        parent::delete($object);
        $this->cache->delete($this->getCacheKey());
    }

    public function deleteAll(array $objects)
    {
        $result = parent::deleteAll($objects);
        $this->cache->delete($this->getCacheKey());
        return $result;
    }

    /**
     * @return string
     */
    protected function getCacheKey()
    {
        return "all_{$this->getTableName()}";
    }
}
