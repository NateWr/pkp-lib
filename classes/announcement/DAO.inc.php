<?php
/**
 * @file classes/announcement/DAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief A class to interact with the announcements database.
 */

namespace PKP\Announcement;

use Announcement;
use Illuminate\Support\Collection;
use PKP\Core\EntityDAOBase;
use stdClass;

class DAO extends EntityDAOBase
{
    /** @copydoc EntityDAOBase::SCHEMA */
    public const SCHEMA = SCHEMA_ANNOUNCEMENT;

    /** @copydoc EntityDAOBase::TABLE */
    public const TABLE = 'announcements';

    /** @copydoc EntityDAOBase::SETTINGS_TABLE */
    public const SETTINGS_TABLE = 'announcement_settings';

    /** @copydoc EntityDAOBase::PRIMARY_KEY_COLUMN */
    public const PRIMARY_KEY_COLUMN = 'announcement_id';

    /** @copydoc EntityDAOBase::PRIMARY_TABLE_COLUMNS */
    public const PRIMARY_TABLE_COLUMNS = [
        'id' => 'announcement_id',
        'assocId' => 'assoc_id',
        'assocType' => 'assoc_type',
        'typeId' => 'type_id',
        'dateExpire' => 'date_expire',
        'datePosted' => 'date_posted',
    ];

    /**
     * @copydoc EntityDAOBase::newDataObject()
     */
    public static function newDataObject(): Announcement
    {
        import('lib.pkp.classes.announcement.Announcement');
        return new Announcement();
    }

    /**
     * Get the total count of rows matching the configured query
     */
    public static function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select('a.' . static::PRIMARY_KEY_COLUMN)
            ->get()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public static function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('a.' . static::PRIMARY_KEY_COLUMN)
            ->pluck('a.' . static::PRIMARY_KEY_COLUMN);
    }

    /**
     * Get a collection of announcements matching the configured query
     */
    public static function getMany(Collector $query): Collection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['a.*'])
            ->get();

        // TODO: This should return an iterator or generator, not the array itself
        return empty($rows)
            ? []
            : $rows->map([static::class, 'fromRow']);
    }

    /**
     * @copydoc EntityDAOBase::_get()
     */
    public static function get(int $id): Announcement
    {
        return parent::_get($id);
    }

    /**
     * @copydoc EntityDAOBase::_fromRow()
     */
    public static function fromRow(stdClass $row): Announcement
    {
        return parent::_fromRow($row);
    }

    /**
     * @copydoc EntityDAOBase::_insert()
     */
    public static function insert(Announcement $announcement): int
    {
        return parent::_insert($announcement);
    }

    /**
     * @copydoc EntityDAOBase::_update()
     */
    public static function update(Announcement $announcement)
    {
        return parent::_update($announcement);
    }

    /**
     * @copydoc EntityDAOBase::_delete()
     */
    public static function delete(Announcement $announcement): bool
    {
        return parent::_delete($announcement);
    }
}
