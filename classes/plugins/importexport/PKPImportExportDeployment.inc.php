<?php
/**
 * @defgroup classes_plugins_importexport import/export deployment
 */

/**
 * @file classes/plugins/importexport/PKPImportExportDeployment.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPImportExportDeployment
 * @ingroup plugins_importexport
 *
 * @brief Base class configuring the import/export process to an
 * application's specifics.
 */

class PKPImportExportDeployment
{
    /** @var Context The current import/export context */
    public $_context;

    /** @var User The current import/export user */
    public $_user;

    /** @var Submission The current import/export submission */
    public $_submission;

    /** @var PKPPublication The current import/export publication */
    public $_publication;

    /** @var array The processed import objects IDs */
    public $_processedObjectsIds = [];

    /** @var array Warnings keyed by object IDs */
    public $_processedObjectsErrors = [];

    /** @var array Errors keyed by object IDs */
    public $_processedObjectsWarnings = [];

    /** @var array Connection between the file from the XML import file and the new IDs after they are imported */
    public $_fileDBIds;

    /** @var array Connection between the submission file IDs from the XML import file and the new IDs after they are imported */
    public $_submissionFileDBIds;

    /** @var array Connection between the author id from the XML import file and the DB file IDs */
    public $_authorDBIds;

    /** @var string Base path for the import source */
    public $_baseImportPath = '';

    /**
     * Constructor
     *
     * @param $context Context
     * @param $user User optional
     */
    public function __construct($context, $user = null)
    {
        $this->setContext($context);
        $this->setUser($user);
        $this->setSubmission(null);
        $this->setPublication(null);
        $this->setFileDBIds([]);
        $this->setSubmissionFileDBIds([]);
        $this->_processedObjectsIds = [];
    }

    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the submission node name
     *
     * @return string
     */
    public function getSubmissionNodeName()
    {
        assert(false);
    }

    /**
     * Get the submissions node name
     *
     * @return string
     */
    public function getSubmissionsNodeName()
    {
        assert(false);
    }

    /**
     * Get the representation node name
     */
    public function getRepresentationNodeName()
    {
        assert(false);
    }

    /**
     * Get the namespace URN
     *
     * @return string
     */
    public function getNamespace()
    {
        assert(false);
    }

    /**
     * Get the schema filename.
     *
     * @return string
     */
    public function getSchemaFilename()
    {
        assert(false);
    }


    //
    // Getter/setters
    //
    /**
     * Set the import/export context.
     *
     * @param $context Context
     */
    public function setContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Get the import/export context.
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->_context;
    }

    /**
     * Set the import/export submission.
     *
     * @param $submission Submission
     */
    public function setSubmission($submission)
    {
        $this->_submission = $submission;
        if ($submission) {
            $this->addProcessedObjectId(ASSOC_TYPE_SUBMISSION, $submission->getId());
        }
    }

    /**
     * Get the import/export submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Set the import/export publication.
     *
     * @param $publication PKPPublication
     */
    public function setPublication($publication)
    {
        $this->_publication = $publication;
        if ($publication) {
            $this->addProcessedObjectId(ASSOC_TYPE_PUBLICATION, $publication->getId());
        }
    }

    /**
     * Get the import/export publication.
     *
     * @return PKPPublication
     */
    public function getPublication()
    {
        return $this->_publication;
    }

    /**
     * Add the processed object ID.
     *
     * @param $assocType integer ASSOC_TYPE_...
     * @param $assocId integer
     */
    public function addProcessedObjectId($assocType, $assocId)
    {
        $this->_processedObjectsIds[$assocType][] = $assocId;
    }

    /**
     * Add the error message to the processed object ID.
     *
     * @param $assocType integer ASSOC_TYPE_...
     * @param $assocId integer
     * @param $errorMsg string
     */
    public function addError($assocType, $assocId, $errorMsg)
    {
        $this->_processedObjectsErrors[$assocType][$assocId][] = $errorMsg;
    }

    /**
     * Add the warning message to the processed object ID.
     *
     * @param $assocType integer ASSOC_TYPE_...
     * @param $assocId integer
     * @param $warningMsg string
     */
    public function addWarning($assocType, $assocId, $warningMsg)
    {
        $this->_processedObjectsWarnings[$assocType][$assocId][] = $warningMsg;
    }

    /**
     * Get the processed objects IDs.
     *
     * @param $assocType integer ASSOC_TYPE_...
     *
     * @return array
     */
    public function getProcessedObjectsIds($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsIds)) {
            return $this->_processedObjectsIds[$assocType];
        }
        return null;
    }

    /**
     * Get the processed objects errors.
     *
     * @param $assocType integer ASSOC_TYPE_...
     *
     * @return array
     */
    public function getProcessedObjectsErrors($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsErrors)) {
            return $this->_processedObjectsErrors[$assocType];
        }
        return null;
    }
    /**
     * Get the processed objects errors.
     *
     * @param $assocType integer ASSOC_TYPE_...
     *
     * @return array
     */

    public function getProcessedObjectsWarnings($assocType)
    {
        if (array_key_exists($assocType, $this->_processedObjectsWarnings)) {
            return $this->_processedObjectsWarnings[$assocType];
        }
        return null;
    }

    /**
     * Remove the processed objects.
     *
     * @param $assocType integer ASSOC_TYPE_...
     */
    public function removeImportedObjects($assocType)
    {
        switch ($assocType) {
            case ASSOC_TYPE_SUBMISSION:
                $processedSubmisssionsIds = $this->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION);
                if (!empty($processedSubmisssionsIds)) {
                    $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
                    foreach ($processedSubmisssionsIds as $submissionId) {
                        if ($submissionId) {
                            $submissionDao->deleteById($submissionId);
                        }
                    }
                }
                break;
        }
    }

    /**
     * Set the import/export user.
     *
     * @param $user User
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    /**
     * Get the import/export user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Get the array of the inserted file DB Ids.
     *
     * @return array
     */
    public function getFileDBIds()
    {
        return $this->_fileDBIds;
    }

    /**
     * Set the array of the inserted file DB Ids.
     *
     * @param $fileDBIds array
     */
    public function setFileDBIds($fileDBIds)
    {
        return $this->_fileDBIds = $fileDBIds;
    }

    /**
     * Get the file DB Id.
     *
     * @param $fileId integer The old file id
     *
     * @return integer The new file id
     */
    public function getFileDBId($fileId)
    {
        if (array_key_exists($fileId, $this->_fileDBIds)) {
            return $this->_fileDBIds[$fileId];
        }
        return null;
    }

    /**
     * Set the file DB Id.
     *
     * @param $fileId integer The old file id
     * @param $DBId integer The new file id
     */
    public function setFileDBId($fileId, $DBId)
    {
        return $this->_fileDBIds[$fileId] = $DBId;
    }

    /**
     * Get the array of the inserted submission file DB Ids.
     *
     * @return array
     */
    public function getSubmissionFileDBIds()
    {
        return $this->_submissionFileDBIds;
    }

    /**
     * Set the array of the inserted submission file DB Ids.
     *
     * @param $submissionFileDBIds array
     */
    public function setSubmissionFileDBIds($submissionFileDBIds)
    {
        return $this->_submissionFileDBIds = $submissionFileDBIds;
    }

    /**
     * Get the submission file DB Id.
     *
     * @return integer The new submission file id
     */
    public function getSubmissionFileDBId($submissionFileDBId)
    {
        if (array_key_exists($submissionFileDBId, $this->_submissionFileDBIds)) {
            return $this->_submissionFileDBIds[$submissionFileDBId];
        }
        return null;
    }

    /**
     * Set the submission file DB Id.
     *
     * @param $submissionFileDBId integer The old submission file id
     * @param $DBId integer The new submission file id
     */
    public function setSubmissionFileDBId($submissionFileDBId, $DBId)
    {
        return $this->_submissionFileDBIds[$submissionFileDBId] = $DBId;
    }

    /**
     * Set the array of the inserted author DB Ids.
     *
     * @param $authorDBIds array
     */
    public function setAuthorDBIds($authorDBIds)
    {
        return $this->_authorDBIds = $authorDBIds;
    }

    /**
     * Get the array of the inserted author DB Ids.
     *
     * @return array
     */
    public function getAuthorDBIds()
    {
        return $this->_authorDBIds;
    }

    /**
     * Get the author DB Id.
     *
     * @param $authorId integer
     *
     * @return integer?
     */
    public function getAuthorDBId($authorId)
    {
        if (array_key_exists($authorId, $this->_authorDBIds)) {
            return $this->_authorDBIds[$authorId];
        }

        return null;
    }

    /**
     * Set the author DB Id.
     *
     * @param $authorId integer
     * @param $DBId integer
     */
    public function setAuthorDBId($authorId, $DBId)
    {
        return $this->_authorDBIds[$authorId] = $DBId;
    }

    /**
     * Set the directory location for the import source
     *
     * @param $path string
     */
    public function setImportPath($path)
    {
        $this->_baseImportPath = $path;
    }

    /**
     * Get the directory location for the import source
     *
     * @return string
     */
    public function getImportPath()
    {
        return $this->_baseImportPath;
    }
}
