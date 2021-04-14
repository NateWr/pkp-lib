<?php
/**
 * @file classes/context/DAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class context
 *
 * @brief A class to interact with the contexts database.
 */

namespace PKP\Context;

use PKP\Core\EntityDAOBase;

abstract class DAO extends EntityDAOBase
{
    /** @copydoc EntityDAOBase::SCHEMA */
    public const SCHEMA = SCHEMA_CONTEXT;

    /**
     * Get the total count of rows matching the configured query
     */
    public static function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select('c.' . static::PRIMARY_KEY_COLUMN)
            ->get()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public static function getIds(Collector $query): \Illuminate\Support\Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('c.' . static::PRIMARY_KEY_COLUMN)
            ->pluck('c.' . static::PRIMARY_KEY_COLUMN);
    }

    /**
     * Get a collection of contexts matching the configured query
     */
    public static function getMany(Collector $query): Collection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['c.*'])
            ->get();

        return Collection::make(function () use ($rows) {
            $rows->each(function ($row) {
                yield static::fromRow($row);
            });
        });
    }
}
