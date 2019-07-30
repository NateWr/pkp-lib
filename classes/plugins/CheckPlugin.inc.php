<?php

/**
 * @file classes/plugins/CheckPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CheckPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

abstract class CheckPlugin extends LazyLoadPlugin {
  /**
   *
   */
  abstract function check($publication, $submission, $allowedLocales, $primaryLocale);
}


