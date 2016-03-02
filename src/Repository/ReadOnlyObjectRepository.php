<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;
use Corma\Exception\BadMethodCallException;

/**
 * Repository that aggressively caches its results and does not permit saving or deleting.
 * find, findByIds, and findAll operate exclusively from cache
 *
 * Not meant for large tables as the entire table is loaded into memory and cache
 */
abstract class ReadOnlyObjectRepository extends ObjectRepository
{
    public function find($id, $useCache = true)
    {
        if($useCache && empty($this->objectByIdCache)) {
            $this->findAll();
        }

        return parent::find($id, $useCache);
    }

    public function findByIds(array $ids, $useCache = true)
    {
        if($useCache && empty($this->objectByIdCache)) {
            $this->findAll();
        }

        return parent::findByIds($ids);
    }

    public function findAll()
    {
        if($this->cache->contains($this->getCacheKey())) {
            return $this->restoreAllFromCache();
        }

        $objects = parent::findAll();

        $this->storeAllInCache($objects);

        return $objects;
    }

    public function save(DataObjectInterface $object)
    {
        throw new BadMethodCallException('Cannot save in a read only repository');
    }

    public function delete(DataObjectInterface $object)
    {
        throw new BadMethodCallException('Cannot delete in a read only repository');
    }

    /**
     * @return string
     */
    protected function getCacheKey()
    {
        return "all_{$this->getTableName()}";
    }

    /**
     * @return DataObjectInterface[]
     */
    protected function restoreAllFromCache()
    {
        $cachedData = $this->cache->fetch($this->getCacheKey());
        $objectsFromCache = [];
        foreach ($cachedData as $data) {
            $object = $this->restoreFromCache($data);
            $objectsFromCache[] = $object;
        }
        return $objectsFromCache;
    }

    /**
     * @param DataObjectInterface[] $objects
     */
    protected function storeAllInCache($objects)
    {
        $dataToCache = [];
        foreach ($objects as $object) {
            $dataToCache[] = $object->getData();
            $this->objectByIdCache[$object->getId()] = $object;
        }
        $this->cache->save($this->getCacheKey(), $dataToCache);
    }
}