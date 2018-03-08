<?php
/**
 * @file classes/security/authorization/SubmissionAssignedRoleOperationPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAssignedRoleOperationPolicy
 * @ingroup security_authorization
 *
 * @brief Policy that checks a handler's operation role assignment against the
 *  user's roles as assigned to a submission.
 */
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SubmissionAssignedRoleOperationPolicy extends AuthorizationPolicy {
	/** @var string Requested operation */
	public $_op = '';

	/** @var array Operation role assignments */
	public $_roleAssignments = array();

	/**
	 * Constructor
	 * @param $op string Requested operation
	 * @param $roleAssignments array Operation role assignments
	 */
	public function __construct($op, $roleAssignments = array()) {
		parent::__construct('user.authorization.submissionRoleBasedAccessDenied');
		$this->_op = $op;
		$this->_roleAssignments = is_array($roleAssignments) ? $roleAssignments : array($roleAssignments);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	public function effect() {
		$assignedRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_ASSIGNED_WORKFLOW_ROLES);
		$allowedRoles = array();
		foreach ($this->_roleAssignments as $roleId => $operations) {
			if (in_array($this->_op, $operations)) {
				$allowedRoles[] = $roleId;
			}
		}
		return count(array_intersect($assignedRoles, $allowedRoles)) ? AUTHORIZATION_PERMIT : AUTHORIZATION_DENY;
	}
}

?>
