<?php
namespace PKP\security\authorization\gates;

use PKP\security\GateFacade as Gate;

class SubmissionGate {

	static public function delete($user, $submission) {

		if (!Gate::allows('with-role', [ROLE_ID_SUB_EDITOR])) {
			return false;
		}

		if (in_array($submission->getData('status'), [STATUS_PUBLISHED, STATUS_SCHEDULED])) {
			return false;
		}

		return true;
	}
}