<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ObjectRepository;
use Corma\Util\PagedQuery;

class ExtendedDataObjectRepository extends ObjectRepository
{
    public function causeUniqueConstraintViolation()
    {
        $this->db->insert($this->getTableName(), ['id'=>999, $this->db->quoteIdentifier('myColumn')=>'value']);
        $this->db->insert($this->getTableName(), ['id'=>999, $this->db->quoteIdentifier('myColumn')=>'value']);
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
}
