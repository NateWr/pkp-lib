<?php

/**
 * @file clases/announcement/Command.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief Commands for modifying announcements
 */

namespace PKP\Announcement;

use Announcement;
use App\Facade\Query;
use Core;
use HookRegistry;
use Request;

class Command
{
    /**
     * Add a new announcement
     *
     * This does not check if the user is authorized to add the announcement, or
     * validate or sanitize this announcement.
     */
    public function add(Announcement $announcement, Request $request): Announcement
    {
        $announcement->setData('datePosted', Core::getCurrentDate());
        DAO::insert($announcement);
        HookRegistry::call('Announcement::add', [$announcement, $request]);

        return $announcement;
    }

    /**
     * Edit an announcement
     *
     * This does not check if the user is authorized to edit the announcement, or
     * validate or sanitize the new announcement values.
     */
    public function edit(Announcement $announcement, array $params, Request $request): Announcement
    {
        $newAnnouncement = DAO::newDataObject();
        $newAnnouncement->_data = array_merge($announcement->_data, $params);

        HookRegistry::call('Announcement::edit', [$newAnnouncement, $announcement, $params, $request]);

        DAO::update($newAnnouncement);
        $newAnnouncement = Query::announcement()->get($newAnnouncement->getId());

        return $newAnnouncement;
    }

    /**
     * Delete an announcement
     *
     * This does not check if the user is authorized to delete the announcement or if
     * the announcement exists.
     */
    public function delete(Announcement $announcement): void
    {
        HookRegistry::call('Announcement::delete::before', [$announcement]);
        DAO::delete($announcement);
        HookRegistry::call('Announcement::delete', [$announcement]);
    }
}
