<?php
/**
 * @file api/v1/contexts/PKPContextHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextHandler
 * @ingroup api_v1_context
 *
 * @brief Base class to handle API requests for contexts (journals/presses).
 */

use APP\Facade\Map;
use APP\Facade\Query;

import('lib.pkp.classes.handler.APIHandler');

class PKPContextHandler extends APIHandler
{
    /** @var string One of the SCHEMA_... constants */
    public $schemaName = SCHEMA_CONTEXT;

    /**
     * @copydoc APIHandler::__construct()
     */
    public function __construct()
    {
        $this->_handlerPath = 'contexts';
        $roles = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/csv',
                    'handler' => [$this, 'getManyCSV'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/custom-props',
                    'handler' => [$this, 'getManyCustomProps'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId}/theme',
                    'handler' => [$this, 'getTheme'],
                    'roles' => $roles,
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [ROLE_ID_SITE_ADMIN],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId}',
                    'handler' => [$this, 'edit'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId}/theme',
                    'handler' => [$this, 'editTheme'],
                    'roles' => $roles,
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId}',
                    'handler' => [$this, 'delete'],
                    'roles' => [ROLE_ID_SITE_ADMIN],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of contexts
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param $args array arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $collector = Query::context()->getCollector()
            ->limit(20)
            ->offset(0);

        // Process query params to format incoming data as needed
        $params = $slimRequest->getQueryParams();
        foreach ($params as $param => $val) {
            switch ($param) {
                case 'isEnabled':
                    $collector->filterByIsEnabled((bool) $val);
                    break;

                case 'searchPhrase':
                    $collector->searchPhrase(trim($val));
                    break;

                case 'count':
                    $collector->limit(min(100, (int) $val));
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;
            }
        }

        \HookRegistry::call('API::contexts::params', [$collector, $slimRequest]);

        // Anyone not a site admin should not be able to access contexts that are
        // not enabled
        if (empty($collector->isEnabled)) {
            $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
            $canAccessDisabledContexts = !empty(array_intersect([ROLE_ID_SITE_ADMIN], $userRoles));
            if (!$canAccessDisabledContexts) {
                return $response->withStatus(403)->withJsonError('api.contexts.403.requestedDisabledContexts');
            }
        }

        $collection = Query::context()->getMany($collector);
        $items = $collection->mapToSchema(Services::get('schema')->getSummaryProps(SCHEMA_CONTEXT), $this->getRequest());

        $data = [
            'itemsMax' => Query::context()->getCount($collector->limit(0)->offset(0)),
            'items' => $items,
        ];

        return $response->withJson($data, 200);
    }

    /**
     * Test a CSV map
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param $args array arguments
     *
     * @return Response
     */
    public function getManyCSV($slimRequest, Slim\Http\Response $response, $args)
    {
        $request = $this->getRequest();

        $collector = Query::context()->getCollector()
            ->limit(20)
            ->offset(0);

        // Process query params to format incoming data as needed
        $params = $slimRequest->getQueryParams();
        foreach ($params as $param => $val) {
            switch ($param) {
                case 'isEnabled':
                    $collector->filterByIsEnabled((bool) $val);
                    break;

                case 'searchPhrase':
                    $collector->searchPhrase(trim($val));
                    break;

                case 'count':
                    $collector->limit(min(100, (int) $val));
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;
            }
        }

        \HookRegistry::call('API::contexts::params', [$collector, $slimRequest]);

        // Anyone not a site admin should not be able to access contexts that are
        // not enabled
        if (empty($collector->isEnabled)) {
            $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
            $canAccessDisabledContexts = !empty(array_intersect([ROLE_ID_SITE_ADMIN], $userRoles));
            if (!$canAccessDisabledContexts) {
                return $response->withStatus(403)->withJsonError('api.contexts.403.requestedDisabledContexts');
            }
        }

        $contexts = Query::context()->getMany($collector);

        $file = Config::getVar('files', 'files_dir') . '/test.csv';
        $fp = fopen($file, 'w');
        fputcsv($fp, ['id', 'name', 'urlPath']);

        $contexts->each(function ($context, $key) use ($fp) {
            fputcsv($fp, [
                $context->getId(),
                $context->getLocalizedData('name'),
                $context->getData('urlPath'),
            ]);
        });

        return $response->withJson($file);
    }

    /**
     * Test a map with custom props
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param $args array arguments
     *
     * @return Response
     */
    public function getManyCustomProps($slimRequest, Slim\Http\Response $response, $args)
    {
        $request = $this->getRequest();

        $collector = Query::context()->getCollector()
            ->limit(20)
            ->offset(0);

        // Process query params to format incoming data as needed
        $params = $slimRequest->getQueryParams();
        foreach ($params as $param => $val) {
            switch ($param) {
                case 'isEnabled':
                    $collector->filterByIsEnabled((bool) $val);
                    break;

                case 'searchPhrase':
                    $collector->searchPhrase(trim($val));
                    break;

                case 'count':
                    $collector->limit(min(100, (int) $val));
                    break;

                case 'offset':
                    $collector->offset((int) $val);
                    break;
            }
        }

        \HookRegistry::call('API::contexts::params', [$collector, $slimRequest]);

        // Anyone not a site admin should not be able to access contexts that are
        // not enabled
        if (empty($collector->isEnabled)) {
            $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
            $canAccessDisabledContexts = !empty(array_intersect([ROLE_ID_SITE_ADMIN], $userRoles));
            if (!$canAccessDisabledContexts) {
                return $response->withStatus(403)->withJsonError('api.contexts.403.requestedDisabledContexts');
            }
        }




        $contexts = Query::context()->getMany($collector);
        $items = $contexts->map(function ($context) {
            return [
                'id' => $context->getId(),
                'name' => $context->getLocalizedData('name'),
                'publishedSubmissions' => Services::get('submission')->getCount(['contextId' => $context->getId(), 'status' => STATUS_PUBLISHED]),
            ];
        });

        return $response->withJson($items);
    }

    /**
     * Get a single context
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function get($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $user = $request->getUser();

        $contextService = Services::get('context');
        $context = $contextService->get((int) $args['contextId']);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // A disabled journal can only be access by site admins and users with a
        // manager role in that journal
        if (!$context->getEnabled()) {
            $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
            if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DaoRegistry::getDao('RoleDAO');
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_MANAGER)) {
                    return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowed');
                }
            }
        }

        $items = \PKP\Context\Collection::make([$context])->mapToSchema(Services::get('schema')->getFullProps(SCHEMA_CONTEXT), $request);

        return $response->withJson($items->first(), 200);
    }

    /**
     * Get the theme and any theme options for a context
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getTheme($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $user = $request->getUser();

        $contextService = Services::get('context');
        $context = $contextService->get((int) $args['contextId']);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // A disabled journal can only be access by site admins and users with a
        // manager role in that journal
        if (!$context->getEnabled()) {
            $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
            if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DaoRegistry::getDao('RoleDAO');
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_MANAGER)) {
                    return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowed');
                }
            }
        }

        $allThemes = PluginRegistry::loadCategory('themes', true);
        $activeTheme = null;
        foreach ($allThemes as $theme) {
            if ($context->getData('themePluginPath') === $theme->getDirName()) {
                $activeTheme = $theme;
                break;
            }
        }

        if (!$activeTheme) {
            return $response->withStatus(404)->withJsonError('api.themes.404.themeUnavailable');
        }

        $data = array_merge(
            $activeTheme->getOptionValues($context->getId()),
            ['themePluginPath' => $theme->getDirName()]
        );

        ksort($data);

        return $response->withJson($data, 200);
    }

    /**
     * Add a context
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function add($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        // This endpoint is only available at the site-wide level
        if ($request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
        }

        $site = $request->getSite();
        $params = $this->convertStringsToSchema(SCHEMA_CONTEXT, $slimRequest->getParsedBody());

        $primaryLocale = $site->getPrimaryLocale();
        $allowedLocales = $site->getSupportedLocales();
        $contextService = Services::get('context');
        $errors = $contextService->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $context = Application::getContextDAO()->newDataObject();
        $context->setAllData($params);
        $context = $contextService->add($context, $request);
        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($contextProps, 200);
    }

    /**
     * Edit a context
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function edit($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $args['contextId'];

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$request->getContext()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
        }

        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        if (!$requestContext && !in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
        }

        $params = $this->convertStringsToSchema(SCHEMA_CONTEXT, $slimRequest->getParsedBody());
        $params['id'] = $contextId;

        $site = $request->getSite();
        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();

        $errors = $contextService->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }
        $context = $contextService->edit($context, $params, $request);

        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($contextProps, 200);
    }

    /**
     * Edit a context's theme and theme options
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function editTheme($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $args['contextId'];

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$request->getContext()) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
        }

        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        if (!$requestContext && !in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
        }

        $params = $slimRequest->getParsedBody();

        // Validate the themePluginPath and allow themes to perform their own validation
        $themePluginPath = empty($params['themePluginPath']) ? null : $params['themePluginPath'];
        if ($themePluginPath !== $context->getData('themePluginPath')) {
            $errors = $contextService->validate(
                VALIDATE_ACTION_EDIT,
                ['themePluginPath' => $themePluginPath],
                $context->getSupportedFormLocales(),
                $context->getPrimaryLocale()
            );
            if (!empty($errors)) {
                return $response->withJson($errors, 400);
            }
            $newContext = $contextService->edit($context, ['themePluginPath' => $themePluginPath], $request);
        }

        // Get the appropriate theme plugin
        $allThemes = PluginRegistry::loadCategory('themes', true);
        $selectedTheme = null;
        foreach ($allThemes as $theme) {
            if ($themePluginPath === $theme->getDirName()) {
                $selectedTheme = $theme;
                break;
            }
        }

        // Run the theme's init() method if a new theme has been selected
        if (isset($newContext)) {
            $selectedTheme->init();
        }

        $errors = $selectedTheme->validateOptions($params, $themePluginPath, $context->getId(), $request);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        // Only accept params that are defined in the theme options
        $options = $selectedTheme->getOptionsConfig();
        foreach ($options as $optionName => $optionConfig) {
            if (!array_key_exists($optionName, $params)) {
                continue;
            }
            $selectedTheme->saveOption($optionName, $params[$optionName], $context->getId());
        }

        // Clear the template cache so that new settings can take effect
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();

        $data = array_merge(
            $selectedTheme->getOptionValues($context->getId()),
            ['themePluginPath' => $themePluginPath]
        );

        ksort($data);

        return $response->withJson($data, 200);
    }

    /**
     * Delete a context
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function delete($slimRequest, $response, $args)
    {

        // This endpoint is only available at the site-wide level
        if ($this->getRequest()->getContext()) {
            return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
        }

        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
            $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedDelete');
        }

        $contextId = (int) $args['contextId'];

        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $contextProps = $contextService->getSummaryProperties($context, [
            'request' => $this->getRequest(),
            'slimRequest' => $slimRequest
        ]);

        $contextService->delete($context);

        return $response->withJson($contextProps, 200);
    }
}
