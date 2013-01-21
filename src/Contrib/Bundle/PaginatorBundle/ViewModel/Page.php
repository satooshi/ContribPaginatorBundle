<?php

namespace Contrib\Bundle\PaginatorBundle\ViewModel;

/**
 * Page represents paginated entity set..
 *
 * @author Kitamura Satoshi <with.no.parachute@gmail.com>
 */
class Page implements \Countable, \IteratorAggregate
{
    /**
     * @var Paginator
     */
    protected $paginator;

    /**
     * Entities.
     *
     * @var mixed
     */
    protected $entities;

    // page numbers

    /**
     * Current page number.
     *
     * @var int
     */
    protected $currentPage;

    /**
     * Previous page number.
     *
     * @var int
     */
    protected $prevPage;

    /**
     * Next page number.
     *
     * @var int
     */
    protected $nextPage;

    // page index

    /**
     * First index of this page (1-based).
     *
     * @var int
     */
    protected $firstIndex;

    /**
     * Last index of this page (1-based).
     *
     * @var int
     */
    protected $lastIndex;

    /**
     * Constructor.
     *
     * @param mixed     $entities  Entities.
     * @param int       $number    Page number.
     * @param Paginator $paginator Paginator.
     */
    public function __construct($entities, $number, $paginator)
    {
        $this->entities    = $entities;
        $this->currentPage = $number;
        $this->paginator   = $paginator;

        $this->setup();
    }

    /**
     * String expression.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('<Page %s of %s>', $this->currentPage, $this->paginator->getEndPage());
    }

    // API

    /**
     * Return whether the given page number is equal to the current page number.
     *
     * @param int $number Page number.
     * @return bool
     */
    public function isCurrent($number)
    {
        return $this->currentPage === $number;
    }

    /**
     * Return whether the paginator has the previous page.
     *
     * @return bool true if the paginator has the previous page, false otherwise.
     */
    public function hasPrevPage()
    {
        return $this->currentPage > 1;
    }

    /**
     * Return whether the paginator has the next page.
     *
     * @return bool true if the paginator has the next page, false otherwise.
     */
    public function hasNextPage()
    {
        return $this->currentPage < $this->paginator->getEndPage();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->entities);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->entities;
    }

    // internal method

    /**
     * Setup index and page numbers.
     *
     * @return void
     */
    protected function setup()
    {
        $resultCount = $this->count();
        $totalCount  = $this->paginator->getTotalCount();
        $limit       = $this->paginator->getLimit();
        $offset      = $this->paginator->calculateOffset($this->currentPage);

        $this->setupIndex($totalCount, $resultCount, $offset, $limit);
        $this->setupPages();
    }

    /**
     * Setup index.
     *
     * @param int $totalCount  Number of entities in all pages.
     * @param int $resultCount Number of entities in this page.
     * @param int $offset      Offset in this page.
     * @param int $limit       Limit number.
     * @return void
     */
    protected function setupIndex($totalCount, $resultCount, $offset, $limit)
    {
        if ($totalCount <= $offset) {
            $ratio = (int)floor($totalCount / $limit);
            $offset = (int)($ratio * $limit);
        }

        $this->firstIndex = $offset + 1; // 1-based index

        if ($resultCount < $limit) {
            $this->lastIndex = $offset + $resultCount;
        } else {
            $this->lastIndex = $offset + $limit;
        }
    }

    /**
     * Setup page numbers.
     *
     * @return void
     */
    protected function setupPages()
    {
        $this->prevPage = $this->currentPage - 1;
        $this->nextPage = $this->currentPage + 1;
    }

    // getter

    /**
     * Return entities.
     *
     * @return mixed
     */
    public function getEntities()
    {
        return $this->entities;
    }

    /**
     * Return the first index of this page.
     *
     * @return int First index of this page.
     */
    public function getFirstIndex()
    {
        return $this->firstIndex;
    }

    /**
     * Return the last index of this page.
     *
     * @return int Last index of this page.
     */
    public function getLastIndex()
    {
        return $this->lastIndex;
    }

    // page numbers

    /**
     * Return this page number.
     *
     * @return int this page number.
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Return previous page number.
     *
     * @return int Previous page number.
     */
    public function getPrevPage()
    {
        if ($this->hasPrevPage()) {
            return $this->prevPage;
        }

        return null;
    }

    /**
     * Return next page number.
     *
     * @return int Next page number.
     */
    public function getNextPage()
    {
        if ($this->hasNextPage()) {
            return $this->nextPage;
        }

        return null;
    }

    // link

    /**
     * Return the page link.
     *
     * @return string The page link.
     */
    public function getCurrentPageLink()
    {
        return $this->paginator->link($this->currentPage);
    }

    /**
     * Return previous page link.
     *
     * @return string Previous page link.
     */
    public function getPrevPageLink()
    {
        return $this->paginator->link($this->getPrevPage());
    }

    /**
     * Return next page link.
     *
     * @return string Next page link.
     */
    public function getNextPageLink()
    {
        return $this->paginator->link($this->getNextPage());
    }
}
