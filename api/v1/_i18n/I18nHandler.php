<?php

/**
 * @file api/v1/-18n/I18nHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I18nHandler
 *
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_i18n;

use PKP\handler\APIHandler;
use PKP\core\APIResponse;
use Slim\Http\Response;
use Slim\Http\Request as SlimRequest;
use PKP\facades\Locale;


class I18nHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = '_i18n';
        $endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern() . "/ui.js",
                    'handler' => [$this, 'getTranslations'],
                ]
            ]
        ];

        $this->_endpoints = $endpoints;

        parent::__construct();
    }

    /**
     * Provides javascript file which includes all translations used in Vue.js UI.
     */
    public function getTranslations(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {

        $translations = Locale::getUITranslationStrings();

        $jsContent = 'pkp.localeKeys = ' . json_encode($translations) . ';';

        $response->getBody()->write($jsContent);
        return $response
        ->withHeader('Content-Type', 'application/javascript');

    }
}