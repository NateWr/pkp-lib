<?php
/**
 * @file classes/publication/Collection.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class publication
 *
 * @brief A class that represents a collection of publications
 */

namespace PKP\Publication;

use APP\Facade\Query;
use APP\Submission;
use DAORegistry;
use HookRegistry;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\Context\Context;
use Request;
use Services;

abstract class Collection extends LazyCollection
{
    /**
     * Map the publications in this collection to an assoc array
     * with all of the properties defined in the schema
     *
     * @param array $props List of schema properties to include
     * @param array $authorUserGroups All author UserGroups configured in the Context
     */
    public function mapToSchema(array $props, Request $request, Submission $submission, Context $submissionContext, array $authorUserGroups): Enumerable
    {
        // Users assigned as reviewers should not receive author details
        $isAnonymized = false;
        if (array_intersect(['authors', 'authorsString', 'authorsStringShort', 'galleys'], $props)) {
            $reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')
                ->getLastReviewRoundReviewAssignmentByReviewer(
                    $submission->getId(),
                    $request->getUser()->getId()
                );
            $isAnonymized = !is_null($reviewAssignment) && $reviewAssignment->getReviewMethod() === SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
        }

        return $this->map(function ($publication) use ($props, $submission, $request, $submissionContext, $authorUserGroups, $isAnonymized) {
            $item = [];

            foreach ($props as $prop) {
                $value = $this->_getAppSchemaProperty($prop, $publication, $submission, $isAnonymized, $submissionContext, $request);
                if ($value) {
                    $item[$prop] = $value;
                    continue;
                }
                switch ($prop) {
                    case '_href':
                        $item[$prop] = Query::publication()->getUrlApi($request, $submissionContext->getData('urlPath'), $submission->getId(), $publication->getId());
                        break;
                    case 'authors':
                        if ($isAnonymized) {
                            $item[$prop] = [];
                        } else {
                            $props = Services::get('schema')->getSummaryProps(SCHEMA_AUTHOR);
                            $item[$prop] = $publication->getData('authors')->mapToSchema($props, $request, $authorUserGroups);
                        }
                        break;
                    case 'authorsString':
                        $item[$prop] = '';
                        if (!$isAnonymized) {
                            $item[$prop] = $publication->getAuthorString($authorUserGroups);
                        }
                        break;
                    case 'authorsStringShort':
                        $item[$prop] = '';
                        if (!$isAnonymized) {
                            $item[$prop] = $publication->getShortAuthorString();
                        }
                        break;
                    case 'citations':
                        $citationDao = DAORegistry::getDAO('CitationDAO'); /* @var $citationDao CitationDAO */
                        $item[$prop] = array_map(
                            function ($citation) {
                                return $citation->getCitationWithLinks();
                            },
                            $citationDao->getByPublicationId($publication->getId())->toArray()
                        );
                        break;
                    case 'fullTitle':
                        $item[$prop] = $publication->getFullTitles();
                        break;
                    default:
                        $item[$prop] = $publication->getData($prop);
                        break;
                }
            }

            $item = Services::get('schema')->addMissingMultilingualValues(SCHEMA_PUBLICATION, $item, $submissionContext->getData('supportedLocales'));

            HookRegistry::call('Publication::collection::mapToSchema', [&$item, $props, $request, $submission, $submissionContext, $authorUserGroups]);

            ksort($item);

            return $item;
        });
    }

    /**
     * Implement this function in a child class to add properties specific to
     * an application, such as Galleys in OJS.
     *
     * This function should return `null` for props that are not specific to
     * the application.
     */
    abstract public function _getAppSchemaProperty(array $prop, Publication $publication, Submission $submission, bool $isAnonymized, Request $request, string $contextUrlPath): mixed;
}
