<?php

namespace Contrib\PaginatorBundle\ViewModel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;

/**
 * Paginator.
 *
 * The ViewModel implements pagination functionality with the
 * Paginator offered by Doctrine ORM.
 *
 * in your controller:
 *
 * $currentPage = $this->getRequest()->query->get('page', 1);
 * $limit       = $this->getRequest()->query->get('size', 10);
 *
 * $queryBuilder = ...; // your QueryBuilder or Query object here
 * $paginator    = new Paginator($this->getRequest(), $queryBuilder, $limit);
 *
 * return array(
 *     'paginator' => $paginator,
 *     'page'      => $paginator->page($currentPage),
 * );
 *
 * @author Kitamura Satoshi <with.no.parachute@gmail.com>
 */
class Paginator
{
    /**
     * Min limit.
     *
     * @var int
     */
    const LIMIT_MIN = 5;

    /**
     * Max limit.
     *
     * @var int
     */
    const LIMIT_MAX = 1000;

    /**
     * Min page size.
     *
     * @var int
     */
    const PAGE_SIZE_MIN = 5;

    /**
     * Max page size.
     *
     * @var int
     */
    const PAGE_SIZE_MAX = 20;

    /**
     * @var \Doctrine\ORM\QueryBuilder
     */
    protected $dataSource;

    protected $countDataSource;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var PageLink
     */
    protected $pageLink;

    /**
     * Limit size per page.
     *
     * @var int
     */
    protected $limit;

    /**
     * Number of pages in pager.
     *
     * @var int
     */
    protected $pageSize;

    /**
     * Total count in all pages.
     *
     * @var int
     */
    protected $totalCount;

    // page numbers
    // if you see page 5, paginator can show you like the following pager
    // start prev ... 3 4 [5] 6 7 ... next end

    /**
     * Current page number.
     *
     * @var int
     */
    protected $currentPage;

    /**
     * First page number displayed in pager control.
     *
     * @var int
     */
    protected $firstPage;

    /**
     * Last page number displayed in pager control.
     *
     * @var int
     */
    protected $lastPage;

    /**
     * Start page number in all pages.
     *
     * @var int
     */
    protected $startPage;

    /**
     * End page number in all pages.
     * @var int
     */
    protected $endPage;

    // optional

    /**
     * Entity ID list if $fetchJoinCollection is set when page() called.
     *
     * @var array
     */
    protected $idList = null;

    /**
     * Constructor.
     *
     * @param Request $request     Request object.
     * @param mixed   $dataSource  QueryBuilder or Query object.
     * @param int     $limit       Limit per page.
     * @param int     $pageSize    Number of displaying pages.
     */
    public function __construct(Request $request, $dataSource, $limit = 10, $pageSize = 10)
    {
        $this->request = $request;
        $this->dataSource = $dataSource;

        if ($limit <= static::LIMIT_MIN) {
            $limit = static::LIMIT_MIN;
        } else if (static::LIMIT_MAX <= $limit) {
            $limit = static::LIMIT_MAX;
        }

        $this->limit = (int)$limit;

        if ($pageSize <= static::PAGE_SIZE_MIN) {
            $pageSize = static::PAGE_SIZE_MIN;
        } else if (static::PAGE_SIZE_MAX <= $pageSize) {
            $pageSize = static::PAGE_SIZE_MAX;
        }

        $this->pageSize = (int)$pageSize;
    }

    // API

    /**
     * Return Page object.
     *
     * @param int  $number              Page number.
     * @param int  $totalCount          Total count of select data source.
     * @param bool $fetchJoinCollection Whether the query joins a collection (true by default).
     * @return \Contrib\CommonBundle\ViewModel\Page
     */
    public function page($number, $totalCount = null, $fetchJoinCollection = true)
    {
        $this->assertPageNumber($number);

        $this->currentPage = (int)$number;

        $dataSource = $this->dataSource($this->currentPage, $fetchJoinCollection);

        if ($totalCount === null) {
            // total count of data source
            // execute  SELECT COUNT() query
            $this->totalCount = count($dataSource);
        } else {
            $this->totalCount = $totalCount;
        }

        $entities = $this->object($dataSource);

        $countPerPage = count($entities);
        $this->assertCountPerPage($countPerPage);

        $this->setup($this->totalCount);

        return new Page($entities, $this->currentPage, $this);
    }

    /**
     * Generate link.
     *
     * @param int $number Page number.
     * @return string
     */
    public function link($number)
    {
        if (!isset($this->pageLink)) {
            $this->pageLink = new PageLink($this->request);
        }

        return $this->pageLink->get($number);
    }

    /**
     * Return whether the paginator can paginate.
     *
     * @return bool true if the paginator can paginate, false otherwise.
     */
    public function canPaginate()
    {
        return 0 !== $this->totalCount && $this->startPage !== $this->endPage;
    }

    /**
     * Return whether the paginator can show ellipsis of start page side (usually left hand side).
     *
     * @return bool true if the paginator can show ellipsis of start page side, false otherwise.
     */
    public function showStartEllipsis()
    {
        return $this->startPage !== $this->firstPage;
    }

    /**
     * Return whether the paginator can show ellipsis of end page side (usually right hand side).
     *
     * @return bool true if the paginator can show ellipsis of end page side, false otherwise.
     */
    public function showEndEllipsis()
    {
        return $this->lastPage !== $this->endPage;
    }

    /**
     * Return whether the paginator can show start page link.
     *
     * @return bool true if the paginator can show start page link, false otherwise.
     */
    public function showStartLink()
    {
        return $this->currentPage !== $this->startPage && $this->currentPage !== ($this->startPage + 1);
    }

    /**
     * Return whether the paginator can show end page link.
     *
     * @return bool true if the paginator can show end page link, false otherwise.
     */
    public function showEndLink()
    {
        return $this->currentPage !== $this->endPage && $this->currentPage !== ($this->endPage - 1);
    }

    // internal method

    /**
     * Assert page number.
     *
     * @param int $number Page number.
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function assertPageNumber($number)
    {
        if (!is_numeric($number) || $number < 1) {
            // page = 0 or page = 'invalid_string'
            throw new NotFoundHttpException('page is invalid');
        }
    }

    /**
     * Assert count per page.
     *
     * @param int $countPerPage Item count per page.
     * @return void
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function assertCountPerPage($countPerPage)
    {
        if ($this->totalCount !== 0 && $countPerPage === 0) {
            throw new NotFoundHttpException('out of page index');
        }
    }

    /**
     * Return data source of entities.
     *
     * @param int    $number              Page number.
     * @param string $fetchJoinCollection Whether the query joins a collection (true by default).
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    protected function dataSource($number, $fetchJoinCollection)
    {
        if ($this->dataSource instanceof QueryBuilder) {
            $query = $this->dataSource->getQuery();
        } else {
            $query = $this->dataSource;
        }

        if ($query instanceof Query) {
            $offset = $this->calculateOffset($number);

            if (is_callable($fetchJoinCollection)) {
                $this->idList = $fetchJoinCollection($offset, $this->limit);

                if (empty($this->idList)) {
                    return array();
                }

                $query->setParameter('idList', $this->idList);

                return new DoctrinePaginator($query, false);
            }

            $query->setFirstResult($offset)->setMaxResults($this->limit);

            return new DoctrinePaginator($query, $fetchJoinCollection);
        }

        return $query;
    }

    /**
     * Return entities.
     *
     * @param mixed $dataSource Data source of entities.
     * @return mixed
     */
    protected function object($dataSource)
    {
        // entities in a page
        if ($dataSource instanceof \IteratorAggregate) {
            // execute SELECT query
            return $dataSource->getIterator();
        }

        return $dataSource;
    }

    /**
     * Setup page numbers.
     *
     * @return void
     */
    protected function setup()
    {
        $max = (int)ceil($this->totalCount / $this->limit);

        $this->startPage = 1;
        $this->endPage = $max;

        if ($max <= $this->pageSize) {
            $this->firstPage = 1;
            $this->lastPage = $max;
        } else {
            $this->setupPages($max);
        }
    }

    /**
     * Setup firstPage and lastPage.
     *
     * @param int $max Max number of pages.
     * @return void
     */
    protected function setupPages($max)
    {
        $prevPages = (int)ceil($this->pageSize / 2);
        $nextPages = (int)floor(($this->pageSize - 1) / 2);

        $this->firstPage = $this->currentPage - $prevPages;
        $this->lastPage = $this->currentPage + $nextPages;

        if ($this->firstPage <= 1) {
            $this->firstPage = 1;
            $this->lastPage = $this->pageSize;
        } else if ($max <= $this->lastPage) {
            $this->firstPage = $max - $this->pageSize + 1;
            $this->lastPage = $max;
        }
    }

    // getter

    /**
     * Calculate offset.
     *
     * @param int $number Page number.
     * @return int
     */
    public function calculateOffset($number)
    {
        return ($number - 1) * $this->limit;
    }

    /**
     * Return limit.
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Return total count in all pages.
     *
     * @return int Total count in all pages.
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    // page numbers

    /**
     * Return all page numbers ranging from the start page to the end page.
     *
     * @return array
     */
    public function getPageRange()
    {
        return range($this->startPage, $this->endPage, 1);
    }

    /**
     * Return display page numbers ranging from the first page to the last page.
     *
     * @return array
     */
    public function getDisplayPageRange()
    {
        return range($this->firstPage, $this->lastPage, 1);
    }

    /**
     * Return the first page number shown in pager control.
     *
     * @return int First page number shown in pager control.
     */
    public function getFirstPage()
    {
        return $this->firstPage;
    }

    /**
     * Return the last page number shown in pager control.
     *
     * @return int Last page number shown in pager control.
     */
    public function getLastPage()
    {
        return $this->lastPage;
    }

    /**
     * Return start page number in all pages.
     *
     * @return int Start page number in all pages.
     */
    public function getStartPage()
    {
        return $this->startPage;
    }

    /**
     * Return end page number in all pages.
     *
     * @return int End page number in all pages.
     */
    public function getEndPage()
    {
        return $this->endPage;
    }

    // link

    /**
     * Return first page link.
     *
     * @return string First page link.
     */
    public function getFirstPageLink()
    {
        return $this->link($this->firstPage);
    }

    /**
     * Return last page link.
     *
     * @return string Last page link.
     */
    public function getLastPageLink()
    {
        return $this->link($this->lastPage);
    }

    /**
     * Return start page link.
     *
     * @return string Start page link.
     */
    public function getStartPageLink()
    {
        return $this->link($this->startPage);
    }

    /**
     * Return end page link.
     *
     * @return string End page link.
     */
    public function getEndPageLink()
    {
        return $this->link($this->endPage);
    }

    // optional

    /**
     * Return entity ID list.
     *
     * @return array
     */
    public function getIdList()
    {
        return $this->idList;
    }
}
