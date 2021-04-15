<?php

/**
 * @file classes/core/Map.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Map
 *
 * @brief A base class for Maps, which map objects from objects to another format
 */

namespace PKP\Core;

use Illuminate\Support\Enumerable;

class Map
{

    public Enumerable $collection;

    protected array $extensions = [];

    public function extend(callable $cb) : self
    {
        $this->extensions[] = $cb;
        return $this;
    }

    protected function withExtensions($output, $input)
    {
        foreach ($this->extensions as $extension) {
            $output = call_user_func($extension, $output, $input, $this);
        }
        return $output;
    }
}
