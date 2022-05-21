<?php
namespace Corma\Util;

use Corma\ObjectMapper;

/**
 * Provides a way to safely execute any number of save / delete operations within a transaction.
 *
 * Can be used in two basic ways, one executeTransaction() will execute the provided callback within a transaction,
 * or secondly you can add objects to save and delete, and call flush to save all objects.
 */
class UnitOfWork
{
    private array $objectsToSave = [];
    private array $objectsToDelete = [];

    public function __construct(private ObjectMapper $orm)
    {
    }

    /**
     * @param object $object
     * @return $this
     */
    public function save(object $object): self
    {
        $class = get_class($object);
        if (isset($this->objectsToSave[$class])) {
            $this->objectsToSave[$class][] = $object;
        } else {
            $this->objectsToSave[$class] = [$object];
        }
        return $this;
    }

    /**
     * @param object[] $objects
     * @return $this
     */
    public function saveAll(array $objects): self
    {
        foreach ($objects as $object) {
            $this->save($object);
        }
        return $this;
    }

    /**
     * @param object $object
     * @return $this
     */
    public function delete(object $object): self
    {
        $class = get_class($object);
        if (isset($this->objectsToDelete[$class])) {
            $this->objectsToDelete[$class][] = $object;
        } else {
            $this->objectsToDelete[$class] = [$object];
        }
        return $this;
    }

    /**
     * @param object[] $objects
     * @return $this
     */
    public function deleteAll(array $objects): self
    {
        foreach ($objects as $object) {
            $this->delete($object);
        }
        return $this;
    }

    /**
     * Wraps the passed function in a transaction and try / catch.
     *
     * If no exceptionHandler is passed the transaction will be rolled back and the exception rethrown.
     *
     * @param \Closure $run
     * @param \Closure|null $exceptionHandler
     * @return mixed The return of the closure passed in
     * @throws \Throwable
     */
    public function executeTransaction(\Closure $run, \Closure $exceptionHandler = null)
    {
        $db = $this->orm->getQueryHelper()->getConnection();
        $db->beginTransaction();
        try {
            $return = $run();
            $db->commit();
            return $return;
        } catch (\Throwable $e) {
            if ($exceptionHandler) {
                $exceptionHandler($e);
            } else {
                $db->rollBack();
                throw $e;
            }
            return null;
        }
    }

    /**
     * Executes all operations
     *
     * @param \Closure|null $exceptionHandler
     * @throws \Throwable
     */
    public function flush(\Closure $exceptionHandler = null): void
    {
        $this->executeTransaction(function () {
            foreach ($this->objectsToSave as $objects) {
                if (count($objects) == 1) {
                    $this->orm->save($objects[0]);
                } else {
                    $this->orm->saveAll($objects);
                }
            }
            foreach ($this->objectsToDelete as $objects) {
                if (count($objects) == 1) {
                    $this->orm->delete($objects[0]);
                } else {
                    $this->orm->deleteAll($objects);
                }
            }
        }, $exceptionHandler);
        $this->objectsToSave = [];
        $this->objectsToDelete = [];
    }
}
