<?php
/**
 * @file classes/announcement/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief A class to get announcements and information about announcements
 */

namespace PKP\Announcement\Maps;

use \Context;
use Illuminate\Support\Enumerable;
use PKP\core\Map as MapBase;
use \Request;
use \Services;

class Schema extends MapBase
{
    public bool $isSummary = false;

    public function summarize(Enumerable $collection, Context $context, Request $request) : Enumerable
    {
        $this->collection = $collection;
        $this->isSummary = true;
        $props = Services::get('schema')->getSummaryProps(SCHEMA_ANNOUNCEMENT);
        return $collection->map(function($item) use ($props, $context, $request) {
            $output = $this->summarizeOne($item, $props, $context, $request);
            return $this->withExtensions($output, $item);
        });
    }

    public function map(Enumerable $collection, Context $context, Request $request) : Enumerable
    {
        $this->collection = $collection;
        $summaryProps = Services::get('schema')->getSummaryProps(SCHEMA_ANNOUNCEMENT);
        $fullProps = Services::get('schema')->getFullProps(SCHEMA_ANNOUNCEMENT);
        return $collection->map(function($item) use ($fullProps, $summaryProps, $context, $request) {
            $output = $this->summarizeOne($item, $summaryProps, $context, $request);
            foreach ($fullProps as $prop) {
                if (isset($output[$prop])) {
                    continue;
                }
                switch ($prop) {
                    case 'age':
                        $then = new DateTime($item->getData('datePosted'));
                        $now = new DateTime();
                        $output[$prop] = $now->diff($then)->format("%a");
                        break;
                    case 'publishedUrl':
                        $output[$prop] = $request->getDispatcher()->url(
                            $request,
                            \PKPApplication::ROUTE_API,
                            $context->getData('urlPath'),
                            'announcements/' . $item->getId()
                        );
                    default:
                        $output[$prop] = $item->getData($prop);
                        break;
                }
            }
            return $this->withExtensions($output, $item);
        });
    }

    /**
     * Map the summary properties of one announcement
     *
     * @param array $props The props to include in the summary as defined in the schema
     */
    protected function summarizeOne(\Announcement $item, array $props, Context $context, Request $request) : array
    {
        $summary = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $summary[$prop] = $request->getDispatcher()->url(
                        $request,
                        \PKPApplication::ROUTE_API,
                        $context->getData('urlPath'),
                        'announcements/' . $item->getId()
                    );
                    break;
                default:
                    $summary[$prop] = $item->getData($prop);
                    break;
            }
        }
        return $summary;
    }
}
