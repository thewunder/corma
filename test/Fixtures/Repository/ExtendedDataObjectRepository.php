<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ObjectRepository;
use Corma\Test\Fixtures\ExtendedDataObject;
use Corma\Test\Fixtures\OtherDataObject;
use Corma\Util\PagedQuery;

class ExtendedDataObjectRepository extends ObjectRepository
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function causeUniqueConstraintViolation(): void
    {
        $this->db->insert($this->getTableName(), ['id'=>999, $this->db->quoteIdentifier('myColumn')=>'value', $this->db->quoteIdentifier('isDeleted')=>0]);
        $this->db->insert($this->getTableName(), ['id'=>999, $this->db->quoteIdentifier('myColumn')=>'value', $this->db->quoteIdentifier('isDeleted')=>0]);
    }

    /**
     * @return ExtendedDataObject[]
     */
    public function findByOtherColumn(string $otherName): array
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['odo.name'=>$otherName]);
        $this->join($qb,'otherDataObject');
        return $this->fetchAll($qb);
    }

    /**
     * @return ExtendedDataObject[]
     */
    public function findByOtherColumnOneToMany(string $otherName): array
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['odo.name'=>$otherName]);
        $this->join($qb,'otherDataObjects');
        return $this->fetchAll($qb);
    }

    /**
     * @return ExtendedDataObject[]
     */
    public function findByOtherColumnManyToMany(string $otherName): array
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['mtmodo.name'=>$otherName]);
        $this->join($qb,'manyToManyOtherDataObjects');
        return $this->fetchAll($qb);
    }

    /**
     * @return ExtendedDataObject[]
     */
    public function findByOtherColumnPolymorphic(string $otherName): array
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', ['p_odo.name'=>$otherName]);
        $this->join($qb,'polymorphic', additional: OtherDataObject::class);
        return $this->fetchAll($qb);
    }

    public function findAllPaged(): PagedQuery
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName());
        return $this->pagedQuery($qb, 5);
    }

    public function findAllSeekPaged(): PagedQuery
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', [], ['myColumn'=>'ASC']);
        return $this->pagedQuery($qb, 5, 'seek');
    }

    public function findAllInvalidPaged(): PagedQuery
    {
        $qb = $this->queryHelper->buildSelectQuery($this->getTableName(), 'main.*', [], ['myColumn'=>'ASC']);
        return $this->pagedQuery($qb, 5, 'invalid');
    }
}
