<?php
/**
 * @file classes/decision/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief Map editorial decisions to the properties defined in their schema
 */

namespace PKP\decision\maps;

use APP\decision\Decision;
use Illuminate\Support\Enumerable;
use PKP\services\PKPSchemaService;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_DECISION;

    /**
     * Map an decision
     *
     * Includes all properties in the decision schema.
     */
    public function map(Decision $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Map a collection of Decisions
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Map schema properties of a Decision to an assoc array
     */
    protected function mapByProperties(array $props, Decision $item): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl('submissions/' . $item->getData('submissionId') . '/decisions/' . $item->getId());
                    break;
                default:
                    $output[$prop] = $item->getData($prop);
                    break;
            }
        }

        ksort($output);

        return $this->withExtensions($output, $item);
    }
}
