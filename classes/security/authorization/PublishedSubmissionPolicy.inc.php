<?php
/**
 * @file classes/security/authorization/internal/PublishedSubmissionPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishedSubmissionPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control public-facing access to a submission. This controls
 *   access to the article, book or preprint landing page and the galleys in
 *   the reader-facing side of the application.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class PublishedSubmissionPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 */
	function __construct($request, &$args) {
		parent::__construct($request, $args, '');

		$callOnDeny = [$request->getDispatcher(), 'handle404', []];
		$this->setAdvice(
			AUTHORIZATION_ADVICE_CALL_ON_DENY,
			$callOnDeny
		);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @copydoc DataObjectRequiredPolicy::dataObjectEffect()
	 *
	 * This authenticate saccess to submissions, publications,
	 * representations (galleys/publication formats) and files
	 * based on the following possible URL structures:
	 *
	 * /view/<submission-best-id>: Current publication landing page
	 * /view/<submission-best-id>/<galley-best-id>: Galley of current publication
	 * /view/<submission-best-id>/version/<version-id>: Specific publication landing page
	 * /view/<submission-best-id>/version/<version-id>/<galley-best-id>: Galley of specific publication
	 * /download/<submission-best-id>/<galley-best-id>/<file-id>: Download a file of a galley (any publication)
	 */
	function dataObjectEffect() {

		if (empty($this->_args) || empty($this->_request->getContext())) {
			return AUTHORIZATION_DENY;
		}

		$urlPathOrId = $this->_args[0];

		$submission = Services::get('submission')->getByUrlPath($urlPathOrId, $this->_request->getContext()->getId());

		if (!$submission && ctype_digit($urlPathOrId)) {
			$submission = Services::get('submission')->get($urlPathOrId);
		}

		if (!$submission) {
			return AUTHORIZATION_DENY;
		}

		$isVersionRequest = isset($this->_args[1]) && $this->_args[1] === 'version';
		if ($isVersionRequest) {
			$publicationId = $this->_args[2] ?? null;
			$representationId = $this->_args[3] ?? null;
		} else {
			$representationId = $this->_args[1] ?? null;
		}

		// Get the requested publication
		if (!empty($publicationId)) {
			foreach ((array) $submission->getData('publications') as $iPublication) {
				if ($iPublication->getId() === (int) $publicationId) {
					$publication = $iPublication;
					break;
				}
			}
		// Galley view/download URLs may not include the publication ID, so get it
		// from the representation
		} elseif (!empty($representationId)) {
			foreach ((array) $submission->getData('publications') as $iPublication) {
				foreach ($iPublication->getRepresentations() as $iRepresentation) {
					if ($iRepresentation->getBestId() == $representationId) {
						$publication = $iPublication;
						break;
					}
				}
				if (isset($publication)) {
					break;
				}
			}
		} else {
			$publication = $submission->getCurrentPublication();
		}

		// A submission without a publication can not be viewed
		if (empty($publication)) {
			return AUTHORIZATION_DENY;
		}

		// Only users with a production stage assignment can view an unpublished submission
		// or publication
		$canPreview = false;
		import('classes.submission.Submission');
		if ($submission->getData('status') !== STATUS_PUBLISHED || $publication->getData('status') !== STATUS_PUBLISHED) {
			if ($this->_request->getUser()) {
				$result = DAORegistry::getDAO('StageAssignmentDAO')->getBySubmissionAndUserIdAndStageId(
					$submission->getId(),
					$this->_request->getUser()->getId(),
					WORKFLOW_STAGE_ID_PRODUCTION
				);
				if (!$result->wasEmpty()) {
					$canPreview = true;

				// Allow unassigned editors and admins to preview an unpublished submission
				} else {
					$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
					$allowedRoles = [ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN];
					if (!empty(array_intersect($allowedRoles, $userRoles))) {
						$canPreview = true;
					}
				}
			}
			if (!$canPreview) {
				return AUTHORIZATION_DENY;
			}
		}

		$this->addAuthorizedContextObject(ASSOC_TYPE_SUBMISSION, $submission);
		$this->addAuthorizedContextObject(ASSOC_TYPE_PUBLICATION, $publication);

		// Get a representation (galley/publication format) if one is requested
		if ($representationId) {
			foreach ($publication->getRepresentations() as $iRepresentation) {
				if ($iRepresentation->getBestId() == $representationId) {
					$representation = $iRepresentation;
					break;
				}
			}

			// Deny if the requested representation does not exist
			if (empty($representation)) {
				return AUTHORIZATION_DENY;
			}

			$this->addAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION, $representation);
		}

		return AUTHORIZATION_PERMIT;
	}
}


