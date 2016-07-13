<?php
namespace Corma\Repository;

use Corma\DataObject\DataObjectInterface;
use Corma\Exception\BadMethodCallException;

/**
 * Repository that aggressively caches its results so that find, findByIds, and findAll operate exclusively from cache.
 *
 * Not meant for large tables as the entire table is loaded into a single cache entry.
 *
 * Saving and deleting in not permitted.
 */
abstract class ReadOnlyObjectRepository extends AggressiveCachingObjectRepository
{
    public function save(DataObjectInterface $object)
    {
        throw new BadMethodCallException('Cannot save in a read only repository');
    }

    public function saveAll(array $objects)
    {
        throw new BadMethodCallException('Cannot save in a read only repository');
    }

    public function delete(DataObjectInterface $object)
    {
        throw new BadMethodCallException('Cannot delete in a read only repository');
    }

    public function deleteAll(array $objects)
    {
        throw new BadMethodCallException('Cannot delete in a read only repository');
    }
}
