<?php

namespace Contrib\Bundle\PaginatorBundle\ViewModel;

use Symfony\Component\HttpFoundation\Request;

/**
 * Page link generator.
 *
 * @author Kitamura Satoshi <with.no.parachute@gmail.com>
 */
class PageLink
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * Query string parameters.
     *
     * @var array
     */
    protected $query;

    /**
     * Page links.
     *
     * @var array
     */
    protected $links = array();

    /**
     * Constructor.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->query = $request->query->all();
    }

    /**
     * Return page link.
     *
     * @param int $number Page number.
     * @return string
     */
    public function get($number)
    {
        if ($this->has($number)) {
            return $this->links[$number];
        }

        // caching generated page link
        $link = $this->generate($number);
        $this->links[$number] = $link;

        return $link;
    }

    /**
     * Return whether the page link has already been generated.
     *
     * @param int $number Page number.
     * @return bool
     */
    public function has($number)
    {
        return isset($this->links[$number]);
    }

    /**
     * Generate page link.
     *
     * @param int $number Page number.
     * @return string
     */
    protected function generate($number)
    {
        $query = $this->query;
        $query['page'] = $number;

        return $this->queryString($query);
    }

    /**
     * Generate query string.
     *
     * @param array $query Query string parameters (['name' => 'value', ...]).
     * @return string
     */
    protected function queryString(array $query)
    {
        if (empty($query)) {
            return '';
        }

        $params = array();

        foreach ($query as $key => $value) {
            if ('' === $value || null === $value) {
                $params[] = $key;
            } else {
                $params[] = $key . '=' . $value;
            }
        }

        return '?' . implode('&', $params);
    }
}
