<?php

namespace Contrib\Bundle\PaginatorBundle\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Paginator
{
    /**
     * @var int
     *
     * @Assert\Type(type="integer")
     * @Assert\Range(min=1)
     */
    private $page;

    /**
     * @var int
     *
     * @Assert\Type(type="integer")
     * @Assert\Range(min=1)
     */
    private $size;

    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }
}
