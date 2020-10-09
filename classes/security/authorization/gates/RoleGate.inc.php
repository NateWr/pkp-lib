<?php
namespace PKP\security\authorization\gates;

class RoleGate {

	static public function any($user, $roleIds) {
		$roleIds = is_array($roleIds) ? $roleIds : [$roleIds];
		return !empty(array_intersect($roleIds, $user->contextRoles));
	}
}