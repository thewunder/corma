<?php

namespace Corma\Util;

use Corma\Exception\InvalidArgumentException;

/**
 * Uses the more classic limit / offset method of paging through long result sets
 */
class OffsetPagedQuery extends PagedQuery
{
    /** @var int  */
    protected $page;
    /** @var int  */
    protected $prev;
    /** @var int  */
    protected $next;

    /**
     * @param int $page Page number to return
     * @param bool $allResults
     * @return array
     */
    public function getResults($page, bool $allResults = false): array
    {
        if ($page < 1 || ($page > $this->getPages() && $this->getPages() > 0)) {
            throw new InvalidArgumentException("Page must be between 1 and {$this->getPages()}");
        }

        if (!$allResults) {
            $this->page = $page;
            $this->prev = $page > 1 ? $page - 1: 0;
            $this->next = $page < $this->pages ? $page + 1 : 0;

            if ($this->getPages() === 0) {
                return [];
            }

            $this->qb->setMaxResults($this->pageSize)
                ->setFirstResult(($page-1) * $this->pageSize);
        }

        $statement = $this->qb->execute();
        return $this->objectManager->fetchAll($statement);
    }

    /**
     * Get the current page number
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Get the previous page number
     *
     * @return int
     */
    public function getPrev(): int
    {
        return $this->prev;
    }

    /**
     * Get the next page number
     *
     * @return int
     */
    public function getNext(): int
    {
        return $this->next;
    }

    public function current()
    {
        if($this->page === null) {
            $this->page = 1;
        }

        return $this->getResults($this->page);
    }

    public function next()
    {
        $this->page++;
    }

    public function key()
    {
        return $this->page;
    }

    public function valid()
    {
        return $this->page >= 1 && $this->page <= $this->pages;
    }

    public function rewind()
    {
        $this->page = 1;
    }
}
