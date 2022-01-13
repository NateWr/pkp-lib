<?php

/**
 * @file classes/context/Context.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Context
 * @ingroup core
 *
 * @brief Basic class describing a context.
 */

namespace PKP\context;

use APP\core\Application;
use APP\i18n\AppLocale;

use PKP\config\Config;
use PKP\statistics\PKPStatisticsHelper;

// Constant used to distinguish whether metadata is enabled and whether it
// should be requested or required during submission
define('METADATA_DISABLE', 0);
define('METADATA_ENABLE', 'enable');
define('METADATA_REQUEST', 'request');
define('METADATA_REQUIRE', 'require');

abstract class Context extends \PKP\core\DataObject
{
    /**
     * Get the localized name of the context
     *
     * @param string $preferredLocale
     *
     * @return string
     */
    public function getLocalizedName($preferredLocale = null)
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * Set the name of the context
     *
     * @param string $name
     * @param null|mixed $locale
     */
    public function setName($name, $locale = null)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * get the name of the context
     *
     * @param null|mixed $locale
     */
    public function getName($locale = null)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get the contact name for this context
     *
     * @return string
     */
    public function getContactName()
    {
        return $this->getData('contactName');
    }

    /**
     * Set the contact name for this context
     *
     * @param string $contactName
     */
    public function setContactName($contactName)
    {
        $this->setData('contactName', $contactName);
    }

    /**
     * Get the contact email for this context
     *
     * @return string
     */
    public function getContactEmail()
    {
        return $this->getData('contactEmail');
    }

    /**
     * Set the contact email for this context
     *
     * @param string $contactEmail
     */
    public function setContactEmail($contactEmail)
    {
        $this->setData('contactEmail', $contactEmail);
    }

    /**
     * Get context description.
     *
     * @param null|mixed $locale
     *
     * @return string
     */
    public function getDescription($locale = null)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set context description.
     *
     * @param string $description
     * @param string $locale optional
     */
    public function setDescription($description, $locale = null)
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get path to context (in URL).
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getData('urlPath');
    }

    /**
     * Set path to context (in URL).
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->setData('urlPath', $path);
    }

    /**
     * Get enabled flag of context
     *
     * @return int
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set enabled flag of context
     *
     * @param int $enabled
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Return the primary locale of this context.
     *
     * @return string
     */
    public function getPrimaryLocale()
    {
        return $this->getData('primaryLocale');
    }

    /**
     * Set the primary locale of this context.
     */
    public function setPrimaryLocale($primaryLocale)
    {
        $this->setData('primaryLocale', $primaryLocale);
    }
    /**
     * Get sequence of context in site-wide list.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set sequence of context in site table of contents.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('seq', $sequence);
    }

    /**
     * Get the localized description of the context.
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get localized acronym of context
     *
     * @return string
     */
    public function getLocalizedAcronym()
    {
        return $this->getLocalizedData('acronym');
    }

    /**
     * Get the acronym of the context.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getAcronym($locale)
    {
        return $this->getData('acronym', $locale);
    }

    /**
     * Get localized favicon
     *
     * @return string
     */
    public function getLocalizedFavicon()
    {
        $faviconArray = $this->getData('favicon');
        foreach ([AppLocale::getLocale(), AppLocale::getPrimaryLocale()] as $locale) {
            if (isset($faviconArray[$locale])) {
                return $faviconArray[$locale];
            }
        }
        return null;
    }

    /**
     * Get the supported form locales.
     *
     * @return array
     */
    public function getSupportedFormLocales(): ?array
    {
        return $this->getData('supportedFormLocales');
    }

    /**
     * Return associative array of all locales supported by forms on the site.
     * These locales are used to provide a language toggle on the main site pages.
     *
     * @return array
     */
    public function getSupportedFormLocaleNames()
    {
        $supportedLocales = & $this->getData('supportedFormLocaleNames');

        if (!isset($supportedLocales)) {
            $supportedLocales = [];
            $localeNames = & AppLocale::getAllLocales();

            $locales = $this->getSupportedFormLocales();
            if (!isset($locales) || !is_array($locales)) {
                $locales = [];
            }

            foreach ($locales as $localeKey) {
                $supportedLocales[$localeKey] = $localeNames[$localeKey];
            }
        }

        return $supportedLocales;
    }

    /**
     * Get the supported submission locales.
     *
     * @return array
     */
    public function getSupportedSubmissionLocales()
    {
        return $this->getData('supportedSubmissionLocales');
    }

    /**
     * Return associative array of all locales supported by submissions on the
     * site. These locales are used to provide a language toggle on the main
     * site pages.
     *
     * @return array
     */
    public function getSupportedSubmissionLocaleNames()
    {
        $supportedLocales = & $this->getData('supportedSubmissionLocaleNames');

        if (!isset($supportedLocales)) {
            $supportedLocales = [];
            $localeNames = & AppLocale::getAllLocales();

            $locales = $this->getSupportedSubmissionLocales();
            if (!isset($locales) || !is_array($locales)) {
                $locales = [];
            }

            foreach ($locales as $localeKey) {
                $supportedLocales[$localeKey] = $localeNames[$localeKey];
            }
        }

        return $supportedLocales;
    }

    /**
     * Get the supported locales.
     *
     * @return array
     */
    public function getSupportedLocales()
    {
        return $this->getData('supportedLocales');
    }

    /**
     * Return associative array of all locales supported by the site.
     * These locales are used to provide a language toggle on the main site pages.
     *
     * @return array
     */
    public function getSupportedLocaleNames()
    {
        $supportedLocales = & $this->getData('supportedLocaleNames');

        if (!isset($supportedLocales)) {
            $supportedLocales = [];
            $localeNames = & AppLocale::getAllLocales();

            $locales = $this->getSupportedLocales();
            if (!isset($locales) || !is_array($locales)) {
                $locales = [];
            }

            foreach ($locales as $localeKey) {
                $supportedLocales[$localeKey] = $localeNames[$localeKey];
            }
        }

        return $supportedLocales;
    }

    /**
     * Return date or/and time formats available for forms, fallback to the default if not set
     *
     * @param string $format datetime property, e.g., dateFormatShort
     *
     * @return array
     */
    public function getDateTimeFormats($format)
    {
        $data = $this->getData($format) ?? [];
        $fallbackConfigVar = strtolower(preg_replace('/([A-Z])/', '_$1', $format));
        foreach ($this->getSupportedFormLocales() as $supportedLocale) {
            if (!array_key_exists($supportedLocale, $data)) {
                $data[$supportedLocale] = Config::getVar('general', $fallbackConfigVar);
            }
        }

        return $data;
    }

    /**
     * Return localized short date format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedDateFormatShort($locale = null)
    {
        if (is_null($locale)) {
            $locale = AppLocale::getLocale();
        }
        $localizedData = $this->getData('dateFormatShort', $locale);
        if (empty($localizedData)) {
            $localizedData = Config::getVar('general', 'date_format_short');
        }

        return $localizedData;
    }

    /**
     * Return localized long date format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedDateFormatLong($locale = null)
    {
        if (is_null($locale)) {
            $locale = AppLocale::getLocale();
        }
        $localizedData = $this->getData('dateFormatLong', $locale);
        if (empty($localizedData)) {
            $localizedData = Config::getVar('general', 'date_format_long');
        }

        return $localizedData;
    }

    /**
     * Return localized time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedTimeFormat($locale = null)
    {
        if (is_null($locale)) {
            $locale = AppLocale::getLocale();
        }
        $localizedData = $this->getData('timeFormat', $locale);
        if (empty($localizedData)) {
            $localizedData = Config::getVar('general', 'time_format');
        }

        return $localizedData;
    }

    /**
     * Return localized short date & time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see see DateTime::format
     */
    public function getLocalizedDateTimeFormatShort($locale = null)
    {
        if (is_null($locale)) {
            $locale = AppLocale::getLocale();
        }
        $localizedData = $this->getData('datetimeFormatShort', $locale);
        if (empty($localizedData)) {
            $localizedData = Config::getVar('general', 'datetime_format_short');
        }

        return $localizedData;
    }

    /**
     * Return localized long date & time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see see DateTime::format
     */
    public function getLocalizedDateTimeFormatLong($locale = null)
    {
        if (is_null($locale)) {
            $locale = AppLocale::getLocale();
        }
        $localizedData = $this->getData('datetimeFormatLong', $locale);
        if (empty($localizedData)) {
            $localizedData = Config::getVar('general', 'datetime_format_long');
        }

        return $localizedData;
    }

    /**
     * Get the association type for this context.
     *
     * @return int
     */
    abstract public function getAssocType();

    /**
     * @deprecated Most settings should be available from self::getData(). In
     *  other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getSetting($name, $locale = null)
    {
        return $this->getData($name, $locale);
    }

    /**
     * @deprecated Most settings should be available from self::getData(). In
     *  other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getLocalizedSetting($name, $locale = null)
    {
        return $this->getLocalizedData($name, $locale);
    }

    /**
     * Update a context setting value.
     *
     * @param string $name
     * @param string $type optional
     * @param bool $isLocalized optional
     *
     * @deprecated 3.3.0.0
     */
    public function updateSetting($name, $value, $type = null, $isLocalized = false)
    {
        Services::get('context')->edit($this, [$name => $value], Application::get()->getRequest());
    }

    /**
     * Get context main page views.
     *
     * @return int
     */
    public function getViews()
    {
        $application = Application::get();
        return $application->getPrimaryMetricByAssoc(Application::getContextAssocType(), $this->getId());
    }


    //
    // Statistics API
    //
    /**
    * Return all metric types supported by this context.
    *
    * @return array An array of strings of supported metric type identifiers.
    */
    public function getMetricTypes($withDisplayNames = false)
    {
        // Retrieve report plugins enabled for this journal.
        $reportPlugins = PluginRegistry::loadCategory('reports', true, $this->getId());
        if (empty($reportPlugins)) {
            return [];
        }

        // Run through all report plugins and retrieve all supported metrics.
        $metricTypes = [];
        foreach ($reportPlugins as $reportPlugin) {
            $pluginMetricTypes = $reportPlugin->getMetricTypes();
            if ($withDisplayNames) {
                foreach ($pluginMetricTypes as $metricType) {
                    $metricTypes[$metricType] = $reportPlugin->getMetricDisplayType($metricType);
                }
            } else {
                $metricTypes = array_merge($metricTypes, $pluginMetricTypes);
            }
        }

        return $metricTypes;
    }

    /**
    * Returns the currently configured default metric type for this context.
    * If no specific metric type has been set for this context then the
    * site-wide default metric type will be returned.
    *
    * @return null|string A metric type identifier or null if no default metric
    *   type could be identified.
    */
    public function getDefaultMetricType()
    {
        $defaultMetricType = $this->getData('defaultMetricType');

        // Check whether the selected metric type is valid.
        $availableMetrics = $this->getMetricTypes();
        if (empty($defaultMetricType)) {
            if (count($availableMetrics) === 1) {
                // If there is only a single available metric then use it.
                $defaultMetricType = $availableMetrics[0];
            } else {
                // Use the site-wide default metric.
                $application = Application::get();
                $defaultMetricType = $application->getDefaultMetricType();
            }
        } else {
            if (!in_array($defaultMetricType, $availableMetrics)) {
                return null;
            }
        }

        return $defaultMetricType;
    }

    /**
    * Retrieve a statistics report pre-filtered on this context.
    *
    * @see <https://pkp.sfu.ca/wiki/index.php/OJSdeStatisticsConcept#Input_and_Output_Formats_.28Aggregation.2C_Filters.2C_Metrics_Data.29>
    * for a full specification of the input and output format of this method.
    *
    * @param null|integer|array $metricType metrics selection
    * @param int|array $columns column (aggregation level) selection
    * @param array $orderBy order criteria
    * @param null|DBResultRange $range paging specification
    *
    * @return null|array The selected data as a simple tabular
    *  result set or null if metrics are not supported by this context.
    */
    public function getMetrics($metricType = null, $columns = [], $filter = [], $orderBy = [], $range = null)
    {
        // Add a context filter and run the report.
        $filter[PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID] = $this->getId();
        $application = Application::get();
        return $application->getMetrics($metricType, $columns, $filter, $orderBy, $range);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\Context', '\Context');
}
