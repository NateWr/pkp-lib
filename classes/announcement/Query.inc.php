<?php
/**
 * @file classes/announcement/Query.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class announcement
 *
 * @brief A class to get announcements and information about announcements
 */

namespace PKP\Announcement;

use Announcement;
use AppLocale;
use HookRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Services;
use ValidatorFactory;

class Query
{
    public function get(int $id): Announcement
    {
        return DAO::get($id);
    }

    public function getCount(Collector $query): int
    {
        return DAO::getCount($query);
    }

    public function getIds(Collector $query): Collection
    {
        return DAO::getIds($query);
    }

    public function getMany(Collector $query): LazyCollection
    {
        return DAO::getMany($query);
    }

    public function getCollector(): Collector
    {
        return new Collector();
    }

    public function validate(string $action, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $errors = [];

        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        );
        $schemaService = Services::get('schema');

        import('lib.pkp.classes.validation.ValidatorFactory');
        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(DAO::SCHEMA, $allowedLocales),
            [
                'dateExpire.date_format' => __('stats.dateRange.invalidDate'),
            ]
        );

        // Check required fields if we're adding a context
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

        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(DAO::SCHEMA), $allowedLocales);
        }

        HookRegistry::call('Announcement::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }
}
