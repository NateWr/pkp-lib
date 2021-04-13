<?php

/**
 * @file classes/db/DBResultRange.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DBResultRange
 * @ingroup db
 *
 * @brief Container class for range information when retrieving a result set.
 */


class DBResultRange
{
    /** The number of items to display */
    public $count;

    /** The number of pages to skip */
    public $page;

    /** Optional offset if pagination is not used. */
    public $offset;

    /**
     * Constructor.
     * Initialize the DBResultRange.
     *
     * @param null|mixed $offset
     */
    public function __construct($count, $page = 1, $offset = null)
    {
        $this->count = $count;
        $this->page = $page;
        $this->offset = $offset;
    }

    /**
     * Checks to see if the DBResultRange is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        return (($this->count > 0) && ($this->page >= 0))
                || ($this->count > 0 && !is_null($this->offset));
    }

    /**
     * Returns the count of pages to skip.
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Set the count of pages to skip.
     *
     * @param $page int
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Returns the count of items in this range to display.
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set the count of items in this range to display.
     *
     * @param $count int
     */
    public function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Returns the offset of items in this range to display.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set the offset of items in this range to display.
     *
     * @param $offset int
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }
}
