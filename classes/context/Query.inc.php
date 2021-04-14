<?php
/**
 * @file classes/context/Query.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class context
 *
 * @brief A class to get contexts and information about contexts
 */

namespace PKP\Context;

use APP\Context\DAO;
use Application;
use AppLocale;
use Context;
use DAORegistry;
use HookRegistry;
use PluginRegistry;
use Request;
use Services;
use ValidatorFactory;

class Query
{
    public function get(int $id): Context
    {
        return DAO::get($id);
    }

    public function getCount(Collector $query): int
    {
        return DAO::getCount($query);
    }

    public function getIds(Collector $query): \Illuminate\Support\Collection
    {
        return DAO::getIds($query);
    }

    public function getMany(Collector $query): Collection
    {
        return DAO::getMany($query);
    }

    public function getCollector(): Collector
    {
        return new Collector();
    }

    /**
     * Get the public URL to this contxt
     */
    public function getUrl(Context $context, Request $request): string
    {
        return $request->getDispatcher()->url(
            $request,
            ROUTE_PAGE,
            $context->getData('urlPath')
        );
    }

    /**
     * Get the URL to this context's API endpoint
     */
    public function getUrlApi(Context $context, Request $request): string
    {
        return $request->getDispatcher()->url(
            $request,
            ROUTE_API,
            $context->getData('urlPath'),
            'contexts/' . $context->getId()
        );
    }

    public function validate(string $action, array $props, array $allowedLocales, string $primaryLocale): array
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_ADMIN,
            LOCALE_COMPONENT_APP_ADMIN,
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        );
        $schemaService = Services::get('schema');

        import('lib.pkp.classes.validation.ValidatorFactory');
        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(DAO::SCHEMA, $allowedLocales),
            [
                'urlPath.regex' => __('admin.contexts.form.pathAlphaNumeric'),
                'primaryLocale.regex' => __('validator.localeKey'),
                'supportedFormLocales.regex' => __('validator.localeKey'),
                'supportedLocales.regex' => __('validator.localeKey'),
                'supportedSubmissionLocales.*.regex' => __('validator.localeKey'),
            ]
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $action,
            $schemaService->getRequiredProps(DAO::SCHEMA),
            $schemaService->getMultilingualProps(DAO::SCHEMA),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(DAO::SCHEMA), $allowedLocales);

        // Ensure that a urlPath, if provided, does not already exist
        $validator->after(function ($validator) use ($action, $props) {
            if (isset($props['urlPath']) && !$validator->errors()->get('urlPath')) {
                $contextDao = Application::getContextDAO();
                $contextWithPath = $contextDao->getByPath($props['urlPath']);
                if ($contextWithPath) {
                    if (!($action === VALIDATE_ACTION_EDIT
                            && isset($props['id'])
                            && (int) $contextWithPath->getId() === $props['id'])) {
                        $validator->errors()->add('urlPath', __('admin.contexts.form.pathExists'));
                    }
                }
            }
        });

        // Ensure that a urlPath is not 0, because this will cause router problems
        $validator->after(function ($validator) use ($props) {
            if (isset($props['urlPath']) && !$validator->errors()->get('urlPath') && $props['urlPath'] == '0') {
                $validator->errors()->add('urlPath', __('admin.contexts.form.pathRequired'));
            }
        });

        // Ensure that the primary locale is one of the supported locales
        $validator->after(function ($validator) use ($action, $props, $allowedLocales) {
            if (isset($props['primaryLocale']) && !$validator->errors()->get('primaryLocale')) {
                // Check against a new supported locales prop
                if (isset($props['supportedLocales'])) {
                    $newSupportedLocales = (array) $props['supportedLocales'];
                    if (!in_array($props['primaryLocale'], $newSupportedLocales)) {
                        $validator->errors()->add('primaryLocale', __('admin.contexts.form.primaryLocaleNotSupported'));
                    }
                    // Or check against the $allowedLocales
                } elseif (!in_array($props['primaryLocale'], $allowedLocales)) {
                    $validator->errors()->add('primaryLocale', __('admin.contexts.form.primaryLocaleNotSupported'));
                }
            }
        });

        // Ensure that the supported locales are supported by the site
        $validator->after(function ($validator) use ($action, $props) {
            $siteSupportedLocales = Application::get()->getRequest()->getSite()->getData('supportedLocales');
            $localeProps = ['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales'];
            foreach ($localeProps as $localeProp) {
                if (isset($props[$localeProp]) && !$validator->errors()->get($localeProp)) {
                    $unsupportedLocales = array_diff($props[$localeProp], $siteSupportedLocales);
                    if (!empty($unsupportedLocales)) {
                        $validator->errors()->add($localeProp, __('api.contexts.400.localesNotSupported', ['locales' => join(__('common.commaListSeparator'), $unsupportedLocales)]));
                    }
                }
            }
        });

        // If a new file has been uploaded, check that the temporary file exists and
        // the current user owns it
        $user = Application::get()->getRequest()->getUser();
        ValidatorFactory::temporaryFilesExist(
            $validator,
            ['favicon', 'homepageImage', 'pageHeaderLogoImage', 'styleSheet'],
            ['favicon', 'homepageImage', 'pageHeaderLogoImage'],
            $props,
            $allowedLocales,
            $user ? $user->getId() : null
        );

        // If sidebar blocks are passed, ensure the block plugin exists and is
        // enabled
        $validator->after(function ($validator) use ($props) {
            if (!empty($props['sidebar']) && !$validator->errors()->get('sidebar')) {
                $plugins = PluginRegistry::loadCategory('blocks', true);
                foreach ($props['sidebar'] as $pluginName) {
                    if (empty($plugins[$pluginName])) {
                        $validator->errors()->add('sidebar', __('manager.setup.layout.sidebar.invalidBlock', ['name' => $pluginName]));
                    }
                }
            }
        });

        // Ensure the theme plugin is installed and enabled
        $validator->after(function ($validator) use ($props) {
            if (!empty($props['themePluginPath']) && !$validator->errors()->get('themePluginPath')) {
                $plugins = PluginRegistry::loadCategory('themes', true);
                $found = false;
                foreach ($plugins as $plugin) {
                    if ($props['themePluginPath'] === $plugin->getDirName()) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $validator->errors()->add('themePluginPath', __('manager.setup.theme.notFound'));
                }
            }
        });

        // Only allow admins to modify which user groups are disabled for bulk emails
        if (!empty($props['disableBulkEmailUserGroups'])) {
            $user = Application::get()->getRequest()->getUser();
            $validator->after(function ($validator) use ($user) {
                $roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
                if (!$roleDao->userHasRole(CONTEXT_ID_NONE, $user->getId(), ROLE_ID_SITE_ADMIN)) {
                    $validator->errors()->add('disableBulkEmailUserGroups', __('admin.settings.disableBulkEmailRoles.adminOnly'));
                }
            });
        }

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(DAO::SCHEMA), $allowedLocales);
        }

        HookRegistry::call('Context::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }
}
