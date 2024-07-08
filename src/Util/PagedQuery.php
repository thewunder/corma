<?php
namespace Corma\Util;

use Corma\DataObject\ObjectManager;
use Corma\Exception\InvalidArgumentException;
use Corma\QueryHelper\QueryHelperInterface;
use Corma\DBAL\Query\QueryBuilder;

/**
 * Class representing a paged query
 */
abstract class PagedQuery implements \JsonSerializable, \Iterator
{
    public const DEFAULT_PAGE_SIZE = 100;

    public const STRATEGY_OFFSET = 'offset';
    public const STRATEGY_SEEK = 'seek';

    protected ?int $resultCount = null;
    protected ?int $pages = null;

    public function __construct(protected QueryBuilder $qb, protected QueryHelperInterface $queryHelper,
                                protected ObjectManager $objectManager, protected int $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        if ($pageSize < 1) {
            throw new InvalidArgumentException('Page size must be greater than 0');
        }

        $this->resultCount = $queryHelper->getCount($qb, $objectManager->getIdColumn());
        $this->pages = floor($this->resultCount / $this->pageSize);
        if($this->resultCount % $this->pageSize > 0) {
            $this->pages++;
        }
    }

    /**
     * @param mixed $page Starts at 1
     * @return object[]
     */
    abstract public function getResults(mixed $page, bool $allResults = false): array;

    /**
     * Get the total number of result pages
     *
     * @return int
     */
    public function getPages(): int
    {
        return $this->pages;
    }

    /**
     * Get the number of results per page
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Get the total number of results
     *
     * @return int
     */
    public function getResultCount(): int
    {
        return $this->resultCount;
    }

    public function jsonSerialize(): object
    {
        $vars = get_object_vars($this);
        unset($vars['qb'], $vars['objectManager'], $vars['queryHelper']);
        return (object) $vars;
    }
}
