<?php

namespace Corma\Util;

use Corma\DataObject\ObjectManager;
use Corma\Exception\InvalidArgumentException;
use Corma\QueryHelper\QueryHelperInterface;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Uses the more consistent and efficient seek / cursor pagination method that uses data from the last result to move between pages.
 * This method however cannot retrieve a page without retrieving the previous page first.
 * This will modify the order by of your query to add a secondary sort based on ID
 *
 * @see https://use-the-index-luke.com/sql/partial-results/fetch-next-page
 */
class SeekPagedQuery extends PagedQuery
{
    /**
     * @var int internal counter used to know when to stop when using as iterator, not necessarily accurate
     */
    protected int $page = 1;
    protected array $lastResults = [];
    private ?array $sortColumns = null;

    public function __construct(QueryBuilder $qb, QueryHelperInterface $queryHelper, ObjectManager $objectManager, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        if ($qb->getQueryPart('groupBy')) {
            throw new InvalidArgumentException('Seek paged queries do not support group by');
        }

        parent::__construct($qb, $queryHelper, $objectManager, $pageSize);
    }

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

    public function valid(): bool
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

        $result = $qb->executeQuery();
        $results = $this->objectManager->fetchAll($result);

        if (!$allResults) {
            if(!empty($results) && $this->page <= $this->pages) {
                $this->lastResults[] = $this->encodeLastResultData(end($results));
                $this->page++;
            }
        }
        return $results;
    }

    public function jsonSerialize(): object
    {
        $data = parent::jsonSerialize();
        $data->lastResult = $this->key();
        unset($data->lastResults, $data->page);
        return $data;
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
        $identifier = $this->objectManager->getIdColumn();
        if (!isset($columns[$identifier]) && !isset($columns['main.'.$identifier])) {
            $this->sortColumns[$identifier] = 'ASC';
            $this->qb->addOrderBy($this->quotedColumn($identifier), 'ASC');
        }
    }

    private function modifyWhere(QueryBuilder $qb, array $lastResultData): void
    {
        $sortColumns = $this->getSortColumns();
        $identifier = $this->objectManager->getIdColumn();
        $tieBreakerInequalities = [];
        $tieBreakerEqualities = [];

        foreach ($sortColumns as $column => $direction) {
            $value = $this->getLastResultValue($column, $lastResultData);

            $quotedColumn = $this->quotedColumn($column);
            $param = $this->removeTableAlias($column);
            $comparison = $direction == 'ASC' ? '>' : '<';

            if ($column == $identifier || $column == "main.$identifier") {
                $qb->setParameter($param, $value);
                $tieBreakerEqualities[] = "$quotedColumn $comparison :$param";
            } else {
                $this->queryHelper->processWhereQuery($qb, ["$column $comparison=" => $value]);
                $tieBreakerInequalities[] = "$quotedColumn $comparison :$param";
                $tieBreakerEqualities[] = "$quotedColumn = :$param";
            }
        }

        $qb->andWhere($qb->expr()->or(
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
            $column = $this->removeTableAlias($column);
            $lastResultData[$column] = $data[$column];
        }
        return json_encode($lastResultData);
    }

    private function getLastResultValue(string $column, array $lastResultData)
    {
        $column = $this->removeTableAlias($column);
        if (!isset($lastResultData[$column])) {
            throw new InvalidArgumentException('All columns in the order by must be passed');
        }

        return $lastResultData[$column];
    }

    private function quotedColumn(string $column): string
    {
        if (!str_contains($column, '.')) {
            $column = 'main.' . $column;
        }
        return $this->qb->getConnection()->quoteIdentifier($column);
    }

    private function removeTableAlias(string $column): string
    {
        if (str_contains($column, '.')) {
            $column = substr($column, strpos($column, '.') + 1);
        }
        return $column;
    }
}
