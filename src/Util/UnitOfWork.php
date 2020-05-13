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
    private $objectsToSave = [];
    private $objectsToDelete = [];

    /**
     * @var ObjectMapper
     */
    private $orm;

    public function __construct(ObjectMapper $orm)
    {
        $this->orm = $orm;
    }

    /**
     * @param object $object
     * @return $this
     */
    public function save($object)
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
    public function saveAll(array $objects)
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
    public function delete($object)
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
    public function deleteAll(array $objects)
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
     * @param callable $run
     * @param callable|null $exceptionHandler
     * @throws \Throwable
     */
    public function executeTransaction(callable $run, callable $exceptionHandler = null)
    {
        $db = $this->orm->getQueryHelper()->getConnection();
        $db->beginTransaction();
        try {
            $run();
            $db->commit();
        } catch (\Throwable $e) {
            if ($exceptionHandler) {
                $exceptionHandler($e);
            } else {
                $db->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Executes all operations
     *
     * @param callable $exceptionHandler
     * @throws \Throwable
     */
    public function flush(callable $exceptionHandler = null)
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
