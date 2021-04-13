<?php

/**
 * @defgroup form Form
 * Implements a toolkit for the server-side implementation of forms, including
 * initializing forms with presets, reading submitted content, validating
 * content, and saving the results.
 */

/**
 * @file classes/form/Form.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Form
 * @ingroup core
 *
 * @brief Class defining basic operations for handling HTML forms.
 */

import('lib.pkp.classes.form.FormError');
import('lib.pkp.classes.form.FormBuilderVocabulary');

// Import all form validators for convenient use in sub-classes
import('lib.pkp.classes.form.validation.FormValidatorUsername');
import('lib.pkp.classes.form.validation.FormValidatorArray');
import('lib.pkp.classes.form.validation.FormValidatorArrayCustom');
import('lib.pkp.classes.form.validation.FormValidatorBoolean');
import('lib.pkp.classes.form.validation.FormValidatorControlledVocab');
import('lib.pkp.classes.form.validation.FormValidatorCustom');
import('lib.pkp.classes.form.validation.FormValidatorReCaptcha');
import('lib.pkp.classes.form.validation.FormValidatorEmail');
import('lib.pkp.classes.form.validation.FormValidatorInSet');
import('lib.pkp.classes.form.validation.FormValidatorLength');
import('lib.pkp.classes.form.validation.FormValidatorLocale');
import('lib.pkp.classes.form.validation.FormValidatorLocaleEmail');
import('lib.pkp.classes.form.validation.FormValidatorCSRF');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('lib.pkp.classes.form.validation.FormValidatorRegExp');
import('lib.pkp.classes.form.validation.FormValidatorUrl');
import('lib.pkp.classes.form.validation.FormValidatorLocaleUrl');
import('lib.pkp.classes.form.validation.FormValidatorISSN');
import('lib.pkp.classes.form.validation.FormValidatorORCID');

class Form
{
    /** The template file containing the HTML form */
    public $_template;

    /** Associative array containing form data */
    public $_data;

    /** Validation checks for this form */
    public $_checks;

    /** Errors occurring in form validation */
    public $_errors;

    /** Array of field names where an error occurred and the associated error message */
    public $errorsArray;

    /** Array of field names where an error occurred */
    public $errorFields;

    /** Array of errors for the form section currently being processed */
    public $formSectionErrors;

    /** Client-side validation rules **/
    public $cssValidation;

    /** @var string Symbolic name of required locale */
    public $requiredLocale;

    /** @var array Set of supported locales */
    public $supportedLocales;

    /** @var string Default form locale */
    public $defaultLocale;

    /**
     * Constructor.
     *
     * @param $template string the path to the form template file
     * @param null|mixed $requiredLocale
     * @param null|mixed $supportedLocales
     */
    public function __construct($template = null, $callHooks = true, $requiredLocale = null, $supportedLocales = null)
    {
        if ($requiredLocale === null) {
            $requiredLocale = AppLocale::getPrimaryLocale();
        }
        $this->requiredLocale = $requiredLocale;
        if ($supportedLocales === null) {
            $supportedLocales = AppLocale::getSupportedFormLocales();
        }
        $this->supportedLocales = $supportedLocales;

        $this->defaultLocale = AppLocale::getLocale();

        $this->_template = $template;
        $this->_data = [];
        $this->_checks = [];
        $this->_errors = [];
        $this->errorsArray = [];
        $this->errorFields = [];
        $this->formSectionErrors = [];

        if ($callHooks === true) {
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "papergalleyform::Constructor"
            // Note that class names are always lower case.
            HookRegistry::call(strtolower_codesafe(get_class($this)) . '::Constructor', [$this, &$template]);
        }
    }


    //
    // Setters and Getters
    //
    /**
     * Set the template
     *
     * @param $template string
     */
    public function setTemplate($template)
    {
        $this->_template = $template;
    }

    /**
     * Get the template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->_template;
    }

    /**
     * Get the required locale for this form (i.e. the locale for which
     * required fields must be set, all others being optional)
     *
     * @return string
     */
    public function getRequiredLocale()
    {
        return $this->requiredLocale;
    }

    //
    // Public Methods
    //
    /**
     * Display the form.
     *
     * @param $request PKPRequest
     * @param $template string the template to be rendered, mandatory
     *  if no template has been specified on class instantiation.
     */
    public function display($request = null, $template = null)
    {
        $this->fetch($request, $template, true);
    }

    /**
     * Returns a string of the rendered form
     *
     * @param $request PKPRequest
     * @param $template string the template to be rendered, mandatory
     *  if no template has been specified on class instantiation.
     * @param $display boolean
     *
     * @return string the rendered form
     */
    public function fetch($request, $template = null, $display = false)
    {
        // Set custom template.
        if (!is_null($template)) {
            $this->_template = $template;
        }

        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "papergalleyform::display"
        // Note that class names are always lower case.
        $returner = null;
        if (HookRegistry::call(strtolower_codesafe(get_class($this)) . '::display', [$this, &$returner])) {
            return $returner;
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setCacheability(CACHEABILITY_NO_STORE);


        // Attach this form object to the Form Builder Vocabulary for validation to work
        $fbv = $templateMgr->getFBV();
        $fbv->setForm($this);

        $templateMgr->assign(array_merge(
            $this->_data,
            [
                'isError' => !$this->isValid(),
                'errors' => $this->getErrorsArray(),
                'formLocales' => $this->supportedLocales,
                'formLocale' => $this->getDefaultFormLocale(),
            ]
        ));

        if ($display) {
            $templateMgr->display($this->_template);
            $returner = null;
        } else {
            $returner = $templateMgr->fetch($this->_template);
        }

        // Reset the FBV's form in case template manager fetches another template not within a form.
        $fbv->setForm(null);

        return $returner;
    }

    /**
     * Get the value of a form field.
     *
     * @param $key string
     */
    public function getData($key)
    {
        return $this->_data[$key] ?? null;
    }

    /**
     * Set the value of one or several form fields.
     *
     * @param $key string|array If a string, then set a single field. If an associative array, then set many.
     * @param $value mixed
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $aKey => $aValue) {
                $this->setData($aKey, $aValue);
            }
        } else {
            $this->_data[$key] = $value;
        }
    }

    /**
     * Initialize form data for a new form.
     */
    public function initData()
    {
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "papergalleyform::initData"
        // Note that class and function names are always lower
        // case.
        HookRegistry::call(strtolower_codesafe(get_class($this) . '::initData'), [$this]);
    }

    /**
     * Assign form data to user-submitted data.
     * Can be overridden from subclasses.
     */
    public function readInputData()
    {
        // Default implementation does nothing.
    }

    /**
     * Validate form data.
     *
     * @param $callHooks boolean True (default) iff hooks are to be called.
     */
    public function validate($callHooks = true)
    {
        if (!isset($this->errorsArray)) {
            $this->getErrorsArray();
        }

        foreach ($this->_checks as $check) {
            if (!isset($this->errorsArray[$check->getField()]) && !$check->isValid()) {
                if (method_exists($check, 'getErrorFields') && method_exists($check, 'isArray') && call_user_func([&$check, 'isArray'])) {
                    $errorFields = call_user_func([&$check, 'getErrorFields']);
                    for ($i = 0, $count = count($errorFields); $i < $count; $i++) {
                        $this->addError($errorFields[$i], $check->getMessage());
                        $this->errorFields[$errorFields[$i]] = 1;
                    }
                } else {
                    $this->addError($check->getField(), $check->getMessage());
                    $this->errorFields[$check->getField()] = 1;
                }
            }
        }

        if ($callHooks === true) {
            // Call hooks based on the calling entity, assuming
            // this method is only called by a subclass. Results
            // in hook calls named e.g. "papergalleyform::validate"
            // Note that class and function names are always lower
            // case.
            $value = null;
            if (HookRegistry::call(strtolower_codesafe(get_class($this) . '::validate'), [$this, &$value])) {
                return $value;
            }
        }

        if (!defined('SESSION_DISABLE_INIT')) {
            $request = Application::get()->getRequest();
            $user = $request->getUser();

            if (!$this->isValid() && $user) {
                // Create a form error notification.
                import('classes.notification.NotificationManager');
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    NOTIFICATION_TYPE_FORM_ERROR,
                    ['contents' => $this->getErrorsArray()]
                );
            }
        }

        return $this->isValid();
    }

    /**
     * Execute the form's action.
     * (Note that it is assumed that the form has already been validated.)
     *
     * @return mixed Result from the consumer to be passed to the caller.  Send a true-ish result if you want the caller to do something with the return value.
     */
    public function execute(...$functionArgs)
    {
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "papergalleyform::execute"
        // Note that class and function names are always lower
        // case.
        $returner = null;
        HookRegistry::call(strtolower_codesafe(get_class($this) . '::execute'), array_merge([$this], $functionArgs, [&$returner]));
        return $returner;
    }

    /**
     * Get the list of field names that need to support multiple locales
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "papergalleyform::getLocaleFieldNames"
        // Note that class and function names are always lower
        // case.
        $returner = [];
        HookRegistry::call(strtolower_codesafe(get_class($this) . '::getLocaleFieldNames'), [$this, &$returner]);
        return $returner;
    }

    /**
     * Get the default form locale.
     *
     * @return string
     */
    public function getDefaultFormLocale()
    {
        $formLocale = $this->defaultLocale;
        if (!isset($this->supportedLocales[$formLocale])) {
            $formLocale = $this->requiredLocale;
        }
        return $formLocale;
    }

    /**
     * Set the default form locale.
     *
     * @param $defaultLocale string
     */
    public function setDefaultFormLocale($defaultLocale)
    {
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Add a supported locale.
     *
     * @param $supportedLocale string
     */
    public function addSupportedFormLocale($supportedLocale)
    {
        if (!in_array($supportedLocale, $this->supportedLocales)) {
            $site = Application::get()->getRequest()->getSite();
            $siteSupportedLocales = $site->getSupportedLocaleNames();
            if (array_key_exists($supportedLocale, $siteSupportedLocales)) {
                $this->supportedLocales[$supportedLocale] = $siteSupportedLocales[$supportedLocale];
            }
        }
    }

    /**
     * Adds specified user variables to input data.
     *
     * @param $vars array the names of the variables to read
     */
    public function readUserVars($vars)
    {
        // Call hooks based on the calling entity, assuming
        // this method is only called by a subclass. Results
        // in hook calls named e.g. "papergalleyform::readUserVars"
        // Note that class and function names are always lower
        // case.
        HookRegistry::call(strtolower_codesafe(get_class($this) . '::readUserVars'), [$this, &$vars]);
        $request = Application::get()->getRequest();
        foreach ($vars as $k) {
            $this->setData($k, $request->getUserVar($k));
        }
    }

    /**
     * Add a validation check to the form.
     *
     * @param $formValidator FormValidator
     */
    public function addCheck($formValidator)
    {
        $this->_checks[] = & $formValidator;
    }

    /**
     * Add an error to the form.
     * Errors are typically assigned as the form is validated.
     *
     * @param $field string the name of the field where the error occurred
     */
    public function addError($field, $message)
    {
        $this->_errors[] = new FormError($field, $message);
    }

    /**
     * Add an error field for highlighting on form
     *
     * @param $field string the name of the field where the error occurred
     */
    public function addErrorField($field)
    {
        $this->errorFields[$field] = 1;
    }

    /**
     * Check if form passes all validation checks.
     *
     * @return boolean
     */
    public function isValid()
    {
        return empty($this->_errors);
    }

    /**
     * Return set of errors that occurred in form validation.
     * If multiple errors occurred processing a single field, only the first error is included.
     *
     * @return array erroneous fields and associated error messages
     */
    public function getErrorsArray()
    {
        $this->errorsArray = [];
        foreach ($this->_errors as $error) {
            if (!isset($this->errorsArray[$error->getField()])) {
                $this->errorsArray[$error->getField()] = $error->getMessage();
            }
        }
        return $this->errorsArray;
    }

    //
    // Private helper methods
    //
    /**
     * Convert PHP variable (literals or arrays) into HTML containing
     * hidden input fields.
     *
     * @param $name string Name of variable
     * @param $value mixed Value of variable
     * @param $stack array Names of array keys (for recursive calling)
     *
     * @return string HTML hidden form elements describing the parameters.
     */
    public function _decomposeArray($name, $value, $stack)
    {
        $returner = '';
        if (is_array($value)) {
            foreach ($value as $key => $subValue) {
                $newStack = $stack;
                $newStack[] = $key;
                $returner .= $this->_decomposeArray($name, $subValue, $newStack);
            }
        } else {
            $name = htmlentities($name, ENT_COMPAT, LOCALE_ENCODING);
            $value = htmlentities($value, ENT_COMPAT, LOCALE_ENCODING);
            $returner .= '<input type="hidden" name="' . $name;
            while (($item = array_shift($stack)) !== null) {
                $item = htmlentities($item, ENT_COMPAT, LOCALE_ENCODING);
                $returner .= '[' . $item . ']';
            }
            $returner .= '" value="' . $value . "\" />\n";
        }
        return $returner;
    }
}
