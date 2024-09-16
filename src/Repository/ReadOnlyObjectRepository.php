<?php
namespace Corma\Repository;

use Corma\Exception\BadMethodCallException;

/**
 * Repository that aggressively caches its results so that find, findByIds, and findAll operate exclusively from cache.
 *
 * Not meant for large tables as the entire table is loaded into a single cache entry.
 *
 * Saving and deleting is not permitted.
 */
abstract class ReadOnlyObjectRepository extends AggressiveCachingObjectRepository
{
    public function save(object $object, string|\Closure|null ...$saveRelationships): object
    {
        throw new BadMethodCallException('Cannot save in a read only repository');
    }

    public function saveAll(array $objects, string|\Closure|null ...$saveRelationships): int
    {
        throw new BadMethodCallException('Cannot save in a read only repository');
    }

    public function delete(object $object): void
    {
        throw new BadMethodCallException('Cannot delete in a read only repository');
    }

    public function deleteAll(array $objects): int
    {
        throw new BadMethodCallException('Cannot delete in a read only repository');
    }
}
