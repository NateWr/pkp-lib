<?php
/**
 * @file classes/security/authorization/SubmissionAssignmentPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAssignmentPolicy
 * @ingroup security_authorization
 *
 * @brief Policy that checks for user assignment to submission and sets an
 *  array of editorial stages the user is authorized to access. If the user is
 *  not allowed to access any stages, it denies authorization.
 */
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SubmissionAssignmentPolicy extends AuthorizationPolicy {
	/** @var int A specific stage to test against */
	public $stageId = null;

	/**
	 * Constructor
	 * @param $stageId int A specific stage to test against. If the user is not
	 *  authorized to access this stage, authorization will be denied. If empty,
	 *  any allowed stage will be sufficient to be authorized.
	 */
	public function __construct($stageId = null) {
		parent::__construct('user.authorization.accessDenied');
		$this->stageId = $stageId;

	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	public function effect() {
		return $this->dataObjectEffect();
	}

	/**
	 * Store the array of editorial stages the user is authorized to access and
	 * return an authorization response based on whether or not they are allowed
	 * access.
	 *
	 * @return bool
	 */
	public function dataObjectEffect() {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$request = Application::getRequest();
		if (!is_a($submission, 'Submission') || !$request->getUser()) {
			return AUTHORIZATION_DENY;
		}

		$allowedStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		if (!is_array($allowedStages)) {
			$contextId = $submission->getContextId();

			// Get user group IDs for each stage assignment
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndUserId($submission->getId(), $request->getUser()->getId())->toArray();

			// All assigned users are allowed stage access based on assignment
			if (!empty($stageAssignments)) {
				$stageAssignmentGroupIds = array_map(function($stageAssignment) {
					return $stageAssignment->getUserGroupId();
				}, $stageAssignments);

				// Get assigned user groups, excluding non-editorial groups
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				$userGroups = $userGroupDao->getByContextId($contextId)->toArray();
				$excludeNonEditorialRoleIds = array(ROLE_ID_REVIEWER, ROLE_ID_AUTHOR);
				$stageAssignmentUserGroups = array_values(array_filter($userGroups, function($userGroup) use ($excludeNonEditorialRoleIds, $stageAssignmentGroupIds) {
					return !in_array($userGroup->getRoleId(), $excludeNonEditorialRoleIds) && in_array($userGroup->getId(), $stageAssignmentGroupIds);
				}));

				// Get allowed stages for remaining user groups
				$allowedStages = array();
				foreach ($stageAssignmentUserGroups as $userGroup) {
					$stages = $userGroupDao->getAssignedStagesByUserGroupId($contextId, $userGroup->getId());
					$allowedStages = array_merge($allowedStages, array_keys($stages));
				}
				$allowedStages = array_unique($allowedStages);

			// Managers and admins can access all stages when not assigned
			} else {
				$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
				if (in_array(ROLE_ID_MANAGER, $userRoles) || in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
					$allowedStages = Application::getApplicationStages();
				}
			}

			$this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $allowedStages);
		}

		// Check against a specific stage ID when requested
		if ($this->stageId) {
			return in_array($this->stageId, $allowedStages) ? AUTHORIZATION_PERMIT : AUTHORIZATION_DENY;
		}

		return empty($allowedStages) ? AUTHORIZATION_DENY : AUTHORIZATION_PERMIT;
	}
}

?>
