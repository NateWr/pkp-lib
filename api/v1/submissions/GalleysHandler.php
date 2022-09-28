<?php

/**
 * @file api/v1/submissions/GalleysHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

namespace PKP\API\v1\submissions;

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;
use PKP\core\APIResponse;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\galley\maps\Schema;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\GalleyRequiredPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\GenreDAO;
use PKP\submissionFile\SubmissionFile;
use Slim\Http\Request as SlimRequest;

class GalleysHandler extends APIHandler
{
    /** @var array Handlers that must be authorized to write to a publication */
    public array $requiresPublicationWriteAccess = [
        'add',
        'edit',
        'delete',
    ];

    /** @var array Handlers that must be authorized to write to a publication */
    public array $requiresGalley = [
        'get',
        'edit',
        'delete',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'submissions/{submissionId:\d+}/publications/{publicationId:\d+}/galleys';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{galleyId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{galleyId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{galleyId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @param Request $request
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array($routeName, $this->requiresPublicationWriteAccess)) {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        }

        if (in_array($routeName, $this->requiresGalley)) {
            $this->addPolicy(new GalleyRequiredPolicy($request, $args, 'galleyId'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get all galleys for a publication
     */
    public function getMany(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $galleys = Repo::galley()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        return $response->withJson(
            $this->getMap()->summarizeMany($galleys),
            200
        );
    }

    /**
     * Get a galley
     */
    public function get(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        return $response->withJson(
            $this->getMap()->map($this->getAuthorizedContextObject(Application::ASSOC_TYPE_GALLEY)),
            200
        );
    }

    /**
     * Add a new galley
     */
    public function add(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_GALLEY, $slimRequest->getParsedBody());
        $params['publicationId'] = $publication->getId();
        $params['locale'] = $params['locale'] ?? $publication->getData('locale');
        $params['isRemote'] = $params['isRemote'] ?? false;
        $params['seq'] = $params['seq'] ?? $publication->getData('galleys')->count();

        if (empty($params['isRemote'])) {
            if (empty($_FILES)) {
                return $response->withStatus(400)->withJsonError('api.files.400.noUpload');
            }

            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return $this->getUploadErrorResponse($response, $_FILES['file']['error']);
            }

            if (!$params['label']) {
                $params['label'] = Repo::galley()->getLabelFromFile($_FILES['file']['name']);
            }
        }

        $primaryLocale = $publication->getData('locale');
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        $errors = Repo::galley()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $galleyId = Repo::galley()->add(
            Repo::galley()->newDataObject($params)
        );

        $galley = Repo::galley()->get($galleyId);

        if (empty($params['isRemote'])) {
            $fileManager = new FileManager();
            $extension = $fileManager->parseFileExtension($_FILES['file']['name']);

            $submissionDir = Repo::submissionFile()
                ->getSubmissionDir(
                    $context->getId(),
                    $submission->getId()
                );
            $fileId = Services::get('file')->add(
                $_FILES['file']['tmp_name'],
                $submissionDir . '/' . uniqid() . '.' . $extension
            );

            $submissionFilesParams = [];

            $submissionFilesParams['assocType'] = Application::ASSOC_TYPE_GALLEY;
            $submissionFilesParams['assocId'] = $galleyId;
            $submissionFilesParams['fileId'] = $fileId;
            $submissionFilesParams['fileStage'] = SubmissionFile::SUBMISSION_FILE_PROOF;
            $submissionFilesParams['name'] = [$publication->getData('locale') => $params['label']];
            $submissionFilesParams['submissionId'] = $submission->getId();
            $submissionFilesParams['uploaderUserId'] = $this->getRequest()->getUser()->getId();

            // If there is only one primary genre possible, set it automatically
            $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
            $genres = $genreDao->getPrimaryByContextId($context->getId());
            [$firstGenre, $secondGenre] = [$genres->next(), $genres->next()];
            if ($firstGenre && !$secondGenre) {
                $submissionFilesParams['genreId'] = $firstGenre->getId();
            }

            $submissionFile = Repo::submissionFile()->newDataObject($submissionFilesParams);
            $submissionFileId = Repo::submissionFile()->add($submissionFile);

            $galley->setData('submissionFileId', $submissionFileId);
            Repo::galley()->dao->update($galley);
        }

        $galley = Repo::galley()->get($galleyId);

        return $response->withJson($this->getMap()->map($galley), 200);
    }

    /**
     * Edit a galley
     */
    public function edit(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $context = $this->getRequest()->getContext();
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $galley = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_GALLEY);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_GALLEY, $slimRequest->getParsedBody());
        $params['publicationId'] = $publication->getId();

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getData('supportedSubmissionLocales');

        $errors = Repo::galley()->validate($galley, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $temporaryFile = null;
        if (empty($params['isRemote']) && !empty($params['temporaryFileId'])) {
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFile = $temporaryFileManager->getFile($params['temporaryFileId'], $this->getRequest()->getUser()->getId());
            if (!$temporaryFile) {
                return $response->withStatus(400)->withJson(['temporaryFileId' => [__('common.noTemporaryFile')]]);
            }
            $submissionFileId = $this->addGalleyFileFromTemporaryFile($temporaryFile, $galley->getId(), $params['genreId']);
            $params['submissionFileId'] = $submissionFileId;
            unset($params['temporaryFileId']);
        }

        Repo::galley()->edit($galley, $params);

        return $response->withJson($this->getMap()->map($galley), 200);
    }

    /**
     * Delete a galley
     */
    public function delete(SlimRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $galley = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_GALLEY);

        $props = $this->getMap()->map($galley);

        Repo::galley()->delete($galley);

        return $response->withJson($props, 200);
    }

    protected function getMap(): Schema
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_PROOF])
            ->getMany();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toArray();

        return Repo::galley()->getSchemaMap(
            $submission,
            $publication,
            $submissionFiles,
            $genres
        );
    }

    protected function addGalleyFileFromTemporaryFile(TemporaryFile $temporaryFile, int $galleyId, int $genreId): ?int
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $submissionDir = Repo::submissionFile()
            ->getSubmissionDir(
                $request->getContext()->getId(),
                $submission->getId()
            );

        $fileId = Services::get('file')->add(
            $temporaryFile->getFilePath(),
            $submissionDir . '/' . uniqid() . '.' . $extension
        );

        $submissionFile = Repo::submissionFile()->newDataObject([
            'assocId' => $galleyId,
            'assocType' => Application::ASSOC_TYPE_GALLEY,
            'fileId' => $fileId,
            'fileStage' => SubmissionFile::SUBMISSION_FILE_PROOF,
            'genreId' => $genreId,
            'name' => [$context->getPrimaryLocale() => $temporaryFile->getOriginalFileName()],
            'uploaderUserId' => (int) $request->getUser()->getId(),
            'submissionId' => $submission->getId(),
        ]);

        return Repo::submissionFile()->add($submissionFile);
    }
}
