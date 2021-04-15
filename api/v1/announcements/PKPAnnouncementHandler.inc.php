<?php

/**
 * @file api/v1/announcements/PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup api_v1_announcement
 *
 * @brief Handle API requests for announcement operations.
 *
 */
use APP\Facade\Command;
use APP\Facade\Map;
use APP\Facade\Query;

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPAnnouncementHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'announcements';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}/oai',
                    'handler' => [$this, 'getOAI'],
                    'roles' => [ROLE_ID_MANAGER],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [ROLE_ID_MANAGER],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [ROLE_ID_MANAGER],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{announcementId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [ROLE_ID_MANAGER],
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
     * Get a single announcement
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
        $announcement = Query::announcement()->get($args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        // The assocId in announcements should always point to the contextId
        if ($announcement->getData('assocId') !== $request->getContext()->getId()) {
            return $response->withStatus(404)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $items = Map::announcementToSchema()->map(collect([$announcement]), $request->getContext(), $request);

        return $response->withJson($items->first(), 200);
    }

    /**
     * Get a single OAI record for an announcement
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getOAI($slimRequest, \APIResponse $response, $args)
    {
        $request = $this->getRequest();
        $announcement = Query::announcement()->get($args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        // The assocId in announcements should always point to the contextId
        if ($announcement->getData('assocId') !== $request->getContext()->getId()) {
            return $response->withStatus(404)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $xml = new DOMDocument('1.0');
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;

        $nodes = Map::announcementToOAI()->map(collect([$announcement]), $xml, $request->getContext(), $request);
        foreach ($nodes as $node) {
            $xml->appendChild($node);
        }

        $body = $response->getBody();
        $body->write($xml->saveXml());

        return $response->withHeader('Content-Type','application/xml');
    }

    /**
     * Get a collection of announcements
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = Application::get()->getRequest();

        $collector = Query::announcement()->getCollector()
            ->limit(30)
            ->offset(0);

        $requestParams = $slimRequest->getQueryParams();

        // Process query params to format incoming data as needed
        foreach ($requestParams as $param => $val) {
            switch ($param) {
                case 'contextIds':
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    } elseif (!is_array($val)) {
                        $val = [$val];
                    }
                    $collector->filterByContextIds(array_map('intval', $val));
                    break;
                case 'typeIds':
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    } elseif (!is_array($val)) {
                        $val = [$val];
                    }
                    $collector->filterByTypeIds(array_map('intval', $val));
                    break;
                case 'count':
                    $collector->limit((int) $val);
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->searchPhrase($val);
                    break;
            }
        }

        if ($this->getRequest()->getContext()) {
            $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);
        }

        \HookRegistry::call('API::announcements::params', [$collector, $slimRequest]);

        $announcements = Query::announcement()->getMany($collector);

        $items = Map::announcementToSchema()->summarize($announcements, $request->getContext(), $request);

        $itemsMax = Query::announcement()->getCount($collector->limit(0)->offset(0));

        return $response->withJson([
            'itemsMax' => $itemsMax,
            'items' => $items,
        ], 200);
    }

    /**
     * Add an announcement
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

        if (!$request->getContext()) {
            throw new Exception('You can not add an announcement without sending a request to the API endpoint of a particular context.');
        }

        $params = $this->convertStringsToSchema(SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
        $params['assocType'] = Application::get()->getContextAssocType();
        $params['assocId'] = $request->getContext()->getId();

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getSupportedFormLocales();
        $errors = Query::announcement()->validate(Query::VALIDATE_ADD, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $announcement = DAORegistry::getDao('AnnouncementDAO')->newDataObject();
        $announcement->setAllData($params);
        $announcement = Command::announcement()->add($announcement, $request);

        $items = Map::announcementToSchema()->map(collect([$announcement]), $request->getContext(), $request);

        return $response->withJson($items->first(), 200);
    }

    /**
     * Edit an announcement
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

        $announcement = Services::get('announcement')->get((int) $args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to edit an announcement from one context from a different context's endpoint
        if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
            return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $params = $this->convertStringsToSchema(SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
        $params['id'] = $announcement->getId();

        $context = $request->getContext();
        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();
        $errors = Query::announcement()->validate(Query::VALIDATE_EDIT, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $announcement = Command::announcement()->edit($announcement, $params, $request);

        $items = Map::announcementToSchema()->map(collect([$announcement]), $request->getContext(), $request);

        return $response->withJson($items->first(), 200);
    }

    /**
     * Delete an announcement
     *
     * @param $slimRequest Request Slim request object
     * @param $response Response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function delete($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        $announcement = Services::get('announcement')->get((int) $args['announcementId']);

        if (!$announcement) {
            return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
        }

        if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
            throw new Exception('Announcement has an assocType that did not match the context.');
        }

        // Don't allow to delete an announcement from one context from a different context's endpoint
        if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
            return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
        }

        $items = Map::announcementToSchema()->map(collect([$announcement]), $request->getContext(), $request);

        Command::announcement()->delete($announcement);

        return $response->withJson($items->first(), 200);
    }
}
