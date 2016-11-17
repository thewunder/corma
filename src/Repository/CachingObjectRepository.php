<?php
namespace Corma\Repository;

/**
 * Caches individual objects by id, by default for 24 hours
 */
abstract class CachingObjectRepository extends ObjectRepository
{
    public function find($id, $useCache = true)
    {
        if (!$useCache) {
            return parent::find($id, false);
        }
        
        if (!isset($this->objectByIdCache[$id])) {
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

    public function findByIds(array $ids, $useCache = true)
    {
        if (!$useCache) {
            return parent::findByIds($ids, false);
        }

        $objects = [];
        foreach ($ids as $i => $id) {
            if (isset($this->objectByIdCache[$id])) {
                $objects[] = $this->objectByIdCache[$id];
                unset($ids[$i]);
            }
        }

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
            unset($ids[array_search($this->getObjectManger()->getId($object), $ids)]);
        }

        if (empty($ids)) {
            return $objects;
        }
        
        $fromDb = parent::findByIds($ids, false);
        $this->storeMultipleInCache($fromDb);
        return array_merge($objects, $fromDb);
    }

    public function save($object)
    {
        $object = parent::save($object);
        $this->storeInCache($object);
        return $object;
    }

    /**
     * @param object[] $objects
     * @return int
     */
    public function saveAll(array $objects)
    {
        $result = parent::saveAll($objects);
        $this->storeMultipleInCache($objects);
        return $result;
    }

    public function delete($object)
    {
        parent::delete($object);
        $om = $this->getObjectManger();
        $this->cache->delete($this->getCacheKey($om->getId($object)));
    }

    public function deleteAll(array $objects)
    {
        $result = parent::deleteAll($objects);
        $om = $this->getObjectManger();
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


    /**
     * @param object $object
     */
    protected function storeInCache($object)
    {
        $om = $this->getObjectManger();
        $id = $om->getId($object);
        $this->cache->save($this->getCacheKey($id), $om->extract($object), $this->getCacheLifetime());
    }

    /**
     * @param object[] $objects
     */
    protected function storeMultipleInCache(array $objects)
    {
        $data = [];
        $om = $this->getObjectManger();
        foreach ($objects as $object) {
            $id = $om->getId($object);
            $data[$this->getCacheKey($id)] = $om->extract($object);
        }
        $this->cache->saveMultiple($data, $this->getCacheLifetime());
    }

    /**
     * @param string $id
     * @return string
     */
    protected function getCacheKey($id)
    {
        return $this->getTableName() . "[$id]";
    }
}
