<?php
/**
 * @file classes/galley/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class galley
 *
 * @brief Map galleys to the properties defined in the galley schema
 */

namespace PKP\galley\maps;

use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\galley\Galley;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\SubmissionFile;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;
    public array $genres ;
    public Publication $publication;
    public string $schema = PKPSchemaService::SCHEMA_GALLEY;
    public Submission $submission;
    public Enumerable $submissionFiles;

    public function __construct(Submission $submission, Publication  $publication, Enumerable $submissionFiles, array $genres, Request $request, Context $context, PKPSchemaService $schemaService)
    {
        parent::__construct($request, $context, $schemaService);
        $this->genres = $genres;
        $this->publication = $publication;
        $this->submission = $submission;
        $this->submissionFiles = $submissionFiles;
    }
    /**
     * Map a galley
     *
     * Includes all properties in the galley schema.
     */
    public function map(Galley $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a galley
     *
     * Includes properties with the apiSummary flag in the galley schema.
     */
    public function summarize(Galley $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of galleys
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(fn ($item) => $this->map($item));
    }

    /**
     * Summarize a collection of galleys
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(fn ($item) => $this->summarize($item));
    }

    /**
     * Map schema properties of a galley to an assoc array
     */
    protected function mapByProperties(array $props, Galley $galley): array
    {
        $output = [];
        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'submissions/' . $this->submission->getId() . '/publications/' . $this->publication->getId() . '/galleys/' . $galley->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'doiObject':
                    if ($galley->getData('doiObject')) {
                        $retVal = Repo::doi()->getSchemaMap()->summarize($galley->getData('doiObject'));
                    } else {
                        $retVal = null;
                    }
                    $output[$prop] = $retVal;
                    break;
                case 'submissionFile':
                    $output[$prop] = null;
                    if ($galley->getData('submissionFileId')) {
                        /** @var SubmissionFile $submissionFile */
                        $submissionFile = $this->submissionFiles->first(fn ($s) => $s->getId() === $galley->getData('submissionFileId'));
                        if ($submissionFile) {
                            $output[$prop] = Repo::submissionFile()
                                ->getSchemaMap()
                                ->map($submissionFile, $this->genres);
                        }
                    }
                    break;
                case 'urlPublished':
                    $output['urlPublished'] = $this->request->getDispatcher()->url(
                        $this->request,
                        PKPApplication::ROUTE_PAGE,
                        $this->context->getData('urlPath'),
                        'article',
                        'view',
                        [
                            $this->submission->getBestId(),
                            'version',
                            $this->publication->getId(),
                            $galley->getBestGalleyId()
                        ]
                    );
                    break;
                default:
                    $output[$prop] = $galley->getData($prop);
                    break;
            }
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());

        ksort($output);

        return $this->withExtensions($output, $galley);
    }
}
