<?php

/**
 * @file classes/facade/Map.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Map
 *
 * @brief This facade provides access to all of the query classes shared across all applications
 */

namespace PKP\Facade;

use PKP\Core\Map as BaseMap;
use PKP\Announcement\Maps\Schema as AnnouncementSchema;
use PKP\Announcement\Maps\OAI as AnnouncementOAI;

class Map
{
    public static array $extensions = [];

    public static function announcementToSchema(): AnnouncementSchema
    {
        return self::withExtensions(new AnnouncementSchema());
    }

    public static function announcementToOAI(): AnnouncementOAI
    {
        return self::withExtensions(new AnnouncementOAI());
    }

    public static function extend(string $map, callable $callback)
    {
        if (isset(self::$extensions[$map])) {
            self::$extensions[$map][] = $callback;
        }
        self::$extensions[$map] = [$callback];
    }

    public static function withExtensions(BaseMap $map) : BaseMap
    {
        foreach (self::$extensions as $name => $extensions) {
            if (is_a($map, $name)) {
                foreach ($extensions as $extension) {
                    $map->extend($extension);
                }
            }
        }
        return $map;
    }
}
