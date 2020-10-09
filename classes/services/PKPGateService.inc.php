<?php
namespace PKP\Services;

use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container as  IlluminateContainer;

class PKPGateService {

	public $gate;

	public function __construct() {
		$this->gate = new Gate(
			new IlluminateContainer,
			function() {
				return \Application::get()->getRequest()->getUser();
			}
		);

		$this->gate->define('with-role', [\PKP\security\authorization\gates\RoleGate::class, 'any']);
		$this->gate->define('delete-submission', [\PKP\security\authorization\gates\SubmissionGate::class, 'delete']);
		$this->gate->define('can-test', function($user) {
			return true;
		});
	}
}