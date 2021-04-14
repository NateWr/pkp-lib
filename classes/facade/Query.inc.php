<?php

/**
 * @file clases/facade/Query.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Query
 *
 * @brief This facade provides access to all of the query classes shared across all applications
 */

namespace PKP\Facade;

use PKP\Announcement\Query as Announcement;

class Query
{
    public static function announcement(): Announcement
    {
        return new Announcement();
    }
}
