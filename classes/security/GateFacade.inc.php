<?php
namespace PKP\security;

use Services;

class GateFacade {
	public static function __callStatic($methodName, $args) {
		$gate = Services::get('gate')->gate;
		if (!method_exists($gate, $methodName)) {
			throw new \Exception('Gate method does not exist: ' . $methodName);
		}
		return call_user_func_array([$gate, $methodName], $args);
	}
}