<?php
/**
 * @file classes/context/Collection.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class context
 *
 * @brief A class that represents a collection of contexts (journals, presses or preprint servers)
 */

namespace PKP\Context;

use APP\Facade\Query;
use HookRegistry;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Request;
use Services;

class Collection extends LazyCollection
{
    /**
     * Map the contexts in this collection to an assoc array
     * with all of the properties defined in the schema
     *
     * @param array $props List of schema properties to include
     */
    public function mapToSchema(array $props, Request $request): Enumerable
    {
        return $this->map(function ($context) use ($props, $request) {
            $item = [];

            foreach ($props as $prop) {
                switch ($prop) {
                    case '_href':
                        $item[$prop] = Query::context()->getUrlApi($context, $request);
                        break;
                    case 'url':
                        $item[$prop] = Query::context()->getUrl($context, $request);
                        break;
                    default:
                        $item[$prop] = $context->getData($prop);
                        break;
                }
            }

            $item = Services::get('schema')->addMissingMultilingualValues(SCHEMA_CONTEXT, $item, $context->getSupportedFormLocales());

            HookRegistry::call('Publication::collection::mapToSchema', [&$item, $props, $request]);

            ksort($item);

            return $item;
        });
    }
}
