<?php
/**
 * @file classes/core/interfaces/EntityCollectorInterface.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityCollectorInterface
 *
 * @brief An interface describing the methods an entity's Collector class must implement.
 */

namespace PKP\Core\Interfaces;

interface EntityCollectorInterface
{
    /**
     * Get an instance of \Illuminate\Database\Query\Builder with the
     * configured query
     *
     * This returns an instance of Laravel's query builder. Use this
     * to execute queries on the entity's table that do not already
     * have a query method.
     *
     * The following example shows how to use getQuery after applying
     * query conditions. In this example, the query is used to get
     * only the date of the last three announcements:
     *
     * ```php
     * $dates = Query::announcemennt()
     *   ->filterByContextIds([$contextId])
     *   ->getQueryBuilder()
     *   ->limit(3)
     *   ->pluck('date_posted');
     * ```
     *
     * See: https://laravel.com/docs/7.x/queries
     */
    public function getQueryBuilder(): \Illuminate\Database\Query\Builder;
}
