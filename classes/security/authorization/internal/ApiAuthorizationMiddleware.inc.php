<?php

/**
 * @file classes/security/authorization/internal/ApiAuthorizationMiddleware.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ApiAuthorizationMiddleware
 * @ingroup security_authorization
 *
 * @brief Slim Api middleware that requires an authorized flag be set on responses
 */

class ApiAuthorizationMiddleware {
	/**
	 * Middleware invokable function
	 *
	 * @param SlimRequest $request request
	 * @param SlimResponse $response response
	 * @param callable $next Next middleware
	 * @return boolean|string|unknown
	 */
	public function __invoke($request, $response, $next) {

		$user = Application::get()->getRequest()->getUser();
		if ($user) {
			$context = Application::get()->getRequest()->getContext();
			$userRoles = \DAORegistry::getDAO('RoleDAO')->getByUserId($user->getId(), $context->getId());
			$roleIds = [];
			foreach ($userRoles as $userRole) {
				$roleIds[] = (int) $userRole->getId();
			}
			$user->contextRoles = array_unique($roleIds);
		}

		$response = $next($request, $response);

		if (!$response->isAuthorized && $response->getStatusCode() !== 403) {
			AppLocale::requireComponents(LOCALE_COMPONENT_PKP_API, LOCALE_COMPONENT_APP_API);
			return $response->withStatus(403)->withJsonError('api.403.unauthorized');
		}
		return $response;
	}
}
