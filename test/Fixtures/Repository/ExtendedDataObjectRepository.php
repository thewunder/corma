<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ObjectRepository;
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
