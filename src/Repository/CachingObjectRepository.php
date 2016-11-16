<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;

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
     * @param DataObjectInterface[] $objects
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
        $this->cache->delete($this->getCacheKey($object->getId()));
    }

    public function deleteAll(array $objects)
    {
        $result = parent::deleteAll($objects);
        /** @var DataObjectInterface $object */
        foreach ($objects as $object) {
            $this->cache->delete($this->getCacheKey($object->getId()));
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
     * @param DataObjectInterface $object
     */
    protected function storeInCache(DataObjectInterface $object)
    {
        $this->cache->save($this->getCacheKey($object->getId()), $object->getData(), $this->getCacheLifetime());
    }

    /**
     * @param DataObjectInterface[] $objects
     */
    protected function storeMultipleInCache(array $objects)
    {
        $data = [];
        foreach ($objects as $object) {
            $data[$this->getCacheKey($object->getId())] = $object->getData();
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
