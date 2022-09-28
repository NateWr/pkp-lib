<?php
/**
 * @file classes/galley/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class galley
 *
 * @brief A repository to find and manage galleys.
 */

namespace PKP\galley;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\App;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    public DAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schemaa */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    protected PKPSchemaService $schemaService;


    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Galley
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id): ?Galley
    {
        return $this->dao->get($id);
    }

    /**
     * Get a publication galley by a url path
     */
    public function getByUrlPath(string $urlPath, Publication $publication): ?Galley
    {
        return $this->dao->getByUrlPath($urlPath, $publication);
    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return App::make(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * galleys to their schema
     *
     * @param Enumerable $submissionFiles All submission files might be assigned
     *   to the galleys that will be mapped.
     * @param array $genres All file genres in this context
     */
    public function getSchemaMap(Submission $submission, Publication $publication, Enumerable $submissionFiles, array $genres): maps\Schema
    {
        return app('maps')->withExtensions(
            $this->schemaMap,
            [
                'submission' => $submission,
                'publication' => $publication,
                'submissionFiles' => $submissionFiles,
                'genres' => $genres,
            ]
        );
    }

    /**
     * Validate properties for a galley
     *
     * Perform validation checks on data used to add or edit an galley.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Galley $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            [
                'locale.regex' => __('validator.localeKey'),
                'urlPath.regex' => __('validator.alpha_dash_period'),
            ]
        );

        // Check required fields if we're adding a context
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        $errors = [];

        // The publicationId must match an existing publication that is not yet published
        $validator->after(function ($validator) use ($props) {
            if (isset($props['publicationId']) && !$validator->errors()->get('publicationId')) {
                $publication = Repo::publication()->get($props['publicationId']);
                if (!$publication) {
                    $validator->errors()->add('publicationId', __('galley.publicationNotFound'));
                } elseif (in_array($publication->getData('status'), [Submission::STATUS_PUBLISHED, Submission::STATUS_SCHEDULED])) {
                    $validator->errors()->add('publicationId', __('galley.editPublishedDisabled'));
                }
            }
        });

        $isRemote = $props['isRemote'] ?? ($object ? $object->getData('isRemote') : null);

        // Remote galleys must have a url
        if ($isRemote) {
            $validator->after(function ($validator) use ($props, $object) {
                $urlRemote = $props['urlRemote'] ?? ($object ? $object->getData('urlRemote') : null);
                if (!$urlRemote) {
                    $validator->errors()->add('urlRemote', __('validator.required'));
                }
            });

        // Local galleys must have a file
        } else {
            $validator->after(function ($validator) use ($props, $object) {
                if (isset($props['temporaryFileId']) && !$validator->errors()->get('temporaryFileId')) {
                    $currentUser = Application::get()->getRequest()->getUser();
                    $temporaryFileManager = new TemporaryFileManager();
                    if (!$temporaryFileManager->getFile($props['temporaryFileId'], $currentUser->getId())) {
                        $validator->errors()->add('temporaryFileId', __('common.noTemporaryFile'));
                    }
                } elseif (isset($props['submissionFileId']) && !$validator->errors()->get('submissionFileId')) {
                    $submissionFile = Repo::submissionFile()->get($props['submissionFileId']);
                    if (!$submissionFile) {
                        $validator->errors()->add('submissionFileId', __('galley.fileNotFound'));
                    } elseif ($submissionFile->getData('assocType') !== Application::ASSOC_TYPE_GALLEY || $submissionFile->getData('assocId') !== $object->getId()) {
                        $validator->errors()->add('submissionFileId', __('galley.fileNotValid'));
                    }
                } elseif ($object && !$object->getData('submissionFileId')) {
                    $validator->errors()->add('isRemote', __('galley.notRemote.fileRequired'));
                }
            });
        }

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get($this->dao->schema), $allowedLocales);
        }

        Hook::call('Galley::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Galley $galley): int
    {
        $id = $this->dao->insert($galley);
        Hook::call('Galley::add', [$galley]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Galley $galley, array $params): void
    {
        $newGalley = clone $galley;
        $newGalley->setAllData(array_merge($newGalley->_data, $params));

        Hook::call('Galley::edit', [$newGalley, $galley, $params]);

        $this->dao->update($newGalley);
    }

    /** @copydoc DAO::delete() */
    public function delete(Galley $galley): void
    {
        Hook::call('Galley::delete::before', [$galley]);
        $this->dao->delete($galley);

        // Delete related submission files
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(Application::ASSOC_TYPE_GALLEY, [$galley->getId()])
            ->getMany();

        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->delete($submissionFile);
        }

        Hook::call('Galley::delete', [$galley]);
    }

    /**
     * Get a default galley name based on the name of
     * the uploaded file
     *
     * Tries to get a recognizable name from the file extension,
     * like PDF (.pdf), Word (.doc*), ePub (.epub), HTML (.html).
     *
     * Falls back on the file name itself.
     */
    public function getLabelFromFile(string $filename): string
    {
        $fileManager = new FileManager();
        $extension = strtolower($fileManager->parseFileExtension($filename));

        if ($extension === 'pdf') {
            $label = __('common.pdf');
        } elseif (preg_match(' /html.*/', $extension)) {
            $label = __('common.html');
        } elseif (preg_match(' /doc.*/', $extension)) {
            $label = __('common.wordDocument');
        } elseif ($extension === 'ePub') {
            $label = __('common.ePub');
        } elseif (preg_match(' /xls.*/', $extension)) {
            $label = __('common.excel');
        } elseif ($extension === 'csv') {
            $label = __('common.csv');
        } else {
            $label = $filename;
        }

        Hook::call('Galley::LabelFromFile', [&$label, $filename]);

        return $label;
    }
}
