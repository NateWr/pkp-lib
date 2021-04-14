<?php
/**
 * @file classes/submission/Collection.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A class that represents a collection of submissions
 */

namespace PKP\Submission;

use APP\Facade\Query;
use Exception;
use HookRegistry;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Request;
use Services;

abstract class Collection extends LazyCollection
{
    /**
     * Map the submissions in this collection to an assoc array
     * with all of the properties defined in the schema
     *
     * This method can not be used to map submissions in more than one
     * context. If you need to map submissions in more than one context,
     * segment them into different collections and map each collection.
     *
     * @param array $props List of schema properties to include
     * @param array $authorUserGroups A list of all UserGroups configured in the Context.
     */
    public function mapToSchema(array $props, Request $request, Context $context, array $authorUserGroups): Enumerable
    {
        \AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);

        return $this->map(function ($submission) use ($props, $request, $context, $authorUserGroups) {
            $item = [];
            if ($submission->getData('contextId') !== $context->getId()) {
                throw new Exception('Submission ' . $submission->getId() . ' is not assigned to ' . $context->getId() . '.');
            }

            foreach ($props as $prop) {
                $value = $this->_getAppSchemaProperty($prop, $submission, $request, $context->getData('urlPath'));
                if ($value) {
                    $item[$prop] = $value;
                    continue;
                }
                switch ($prop) {
                    case '_href':
                        $item[$prop] = Query::submission()->getUrlApi($submission->getId(), $context->getData('urlPath'), $request);
                        break;
                    case 'publications':
                        $props = Services::get('schema')->getSummaryProps(SCHEMA_PUBLICATION);
                        $item[$prop] = $submission->getData('publications')->mapToSchema($props, $submission, $context->getData('urlPath'), $authorUserGroups);
                        break;
                    case 'reviewAssignments':
                        $item[$prop] = $this->getPropertyReviewAssignments($submission);
                        break;
                    case 'reviewRounds':
                        $item[$prop] = $this->getPropertyReviewRounds($submission);
                        break;
                    case 'stages':
                        $item[$prop] = $this->getPropertyStages($submission);
                        break;
                    case 'statusLabel':
                        $item[$prop] = __($submission->getStatusKey());
                        break;
                    case 'urlAuthorWorkflow':
                        $item[$prop] = Query::submission()->getUrlAuthorWorkflow($submission->getId(), $context->getData('urlPath'), $request);
                        break;
                    case 'urlEditorialWorkflow':
                        $item[$prop] = Query::submission()->getUrlEditorialWorkflow($submission->getId(), $context->getData('urlPath'), $request);
                        // no break
                    case 'urlWorkflow':
                        $item[$prop] = Query::submission()->getWorkflowUrlByUserRoles($submission);
                        break;
                    default:
                        $item[$prop] = $submission->getData($prop);
                        break;
                }
            }


            $item = Services::get('schema')->addMissingMultilingualValues(SCHEMA_SUBMISSION, $item, $context->getData('supportedSubmissionLocales'));

            HookRegistry::call('Submission::collection::mapToSchema', [&$item, $props, $request, $context, $authorUserGroups]);

            ksort($item);

            return $item;
        });
    }

    /**
     * Add OJS-specific properties when mapping a submission to the schema
     */
    abstract public function _getAppSchemaProperty(array $prop, Submission $submission, Request $request, Context $context): mixed;
}
