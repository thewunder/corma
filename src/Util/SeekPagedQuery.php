<?php

namespace Corma\Util;

use Corma\Exception\InvalidArgumentException;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Uses the more consistent and efficient seek / cursor pagination method that uses data from the last result to move between pages.
 * This method however cannot retrieve a page with out retrieving the previous page first.
 * This will modify the order by of your query to add a secondary sort based on ID
 *
 * @see https://use-the-index-luke.com/sql/partial-results/fetch-next-page
 */
class SeekPagedQuery extends PagedQuery
{
    protected $page = 1;
    protected $lastResults = [];

    private $sortColumns;

    public function current()
    {
        $current = $this->key();
        return $this->getResults($current);
    }

    public function next()
    {
    }

    public function key()
    {
        return $this->lastResults[$this->page-2] ?? null;
    }

    public function valid()
    {
        if(empty($this->lastResults) && $this->page == 1) {
            return $this->resultCount > 0;
        }

        return isset($this->lastResults[$this->page - 2])
            && $this->page > 0 && $this->page <= $this->pages;
    }

    public function rewind()
    {
        $this->page = 1;
        $this->lastResults = [];
    }

    public function getResults($lastResult, bool $allResults = false): array
    {
        $qb = $this->qb;
        $lastResultData = $this->decodeLastResultData($lastResult);

        if (!$allResults) {
            $this->addIdSort();

            $qb = clone $qb;
            if ($this->getPages() === 0) {
                return [];
            }

            if (!empty($lastResultData)) {
                $this->modifyWhere($qb, $lastResultData);
            }

            $qb->setMaxResults($this->pageSize);
        }

        $statement = $qb->execute();
        $results = $this->objectManager->fetchAll($statement);

        if (!$allResults) {
            if(!empty($results) && $this->page <= $this->pages) {
                $this->lastResults[] = $this->encodeLastResultData(end($results));
                $this->page++;
            }
        }
        return $results;
    }

    private function getSortColumns(): array
    {
        if ($this->sortColumns) {
            return $this->sortColumns;
        }

        $orderBy = $this->qb->getQueryPart('orderBy');

        $columns = [];
        $connection = $this->queryHelper->getConnection();
        $quoteChar = $connection->getDatabasePlatform()->getIdentifierQuoteCharacter();
        foreach ($orderBy as $orderByPart) {
            [$column, $dir]= explode(' ', $orderByPart);
            $column = str_replace($quoteChar, '', $column);
            $columns[$column] = $dir;
        }

        return $this->sortColumns = $columns;
    }

    /**
     * In order to work correctly the orderBy must include a unique column
     */
    private function addIdSort(): void
    {
        $columns = $this->getSortColumns();
        $connection = $this->queryHelper->getConnection();
        $identifier = $this->objectManager->getIdColumn();
        if (!isset($columns[$identifier])) {
            $this->sortColumns[$identifier] = 'ASC';
            $this->qb->addOrderBy($connection->quoteIdentifier($identifier), 'ASC');
        }
    }

    private function modifyWhere(QueryBuilder $qb, array $lastResultData): void
    {
        $sortColumns = $this->getSortColumns();
        $identifier = $this->objectManager->getIdColumn();
        $tieBreakerInequalities = [];
        $tieBreakerEqualities = [];

        foreach ($sortColumns as $column => $direction) {
            if (!isset($lastResultData[$column])) {
                throw new InvalidArgumentException('All columns in the order by must be passed');
            }

            $value = $lastResultData[$column];

            $quotedColumn = $this->qb->getConnection()->quoteIdentifier($column);
            if ($column == $identifier) {
                $qb->setParameter(":$identifier", $value);
                $tieBreakerEqualities[] = "$quotedColumn > :$identifier";
            } else if ($direction == 'ASC') {
                $this->queryHelper->processWhereQuery($qb, ["$column >=" => $value]);
                $tieBreakerInequalities[] = "$quotedColumn > :$column";
                $tieBreakerEqualities[] = "$quotedColumn = :$column";
            } else {
                $this->queryHelper->processWhereQuery($qb, ["$column <=" => $value]);
                $tieBreakerInequalities[] = "$quotedColumn < :$column";
                $tieBreakerEqualities[] = "$quotedColumn = :$column";
            }

        }

        $qb->andWhere($qb->expr()->orX(
                new CompositeExpression(CompositeExpression::TYPE_AND, $tieBreakerInequalities),
                new CompositeExpression(CompositeExpression::TYPE_AND, $tieBreakerEqualities)
            )
        );
    }

    private function decodeLastResultData($lastResult): array
    {
        $lastResultData = [];
        if ($lastResult) {
            $lastResultData = json_decode($lastResult, true, 2);
            if (json_last_error()) {
                throw new InvalidArgumentException('Invalid json passed');
            }
        }
        return $lastResultData;
    }

    private function encodeLastResultData($object): string
    {
        $data = $this->objectManager->extract($object);
        $lastResultData = [];
        foreach ($this->getSortColumns() as $column => $dir) {
            $lastResultData[$column] = $data[$column];
        }
        return json_encode($lastResultData);
    }

    public function jsonSerialize()
    {
        $data = parent::jsonSerialize();
        $data->lastResult = $this->key();
        return $data;
    }
}
