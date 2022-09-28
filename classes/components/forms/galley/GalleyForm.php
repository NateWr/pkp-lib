<?php
/**
 * @file classes/components/form/galley/GalleyForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding or editing a galley
 */

namespace PKP\components\forms\galley;

use APP\core\Application;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\galley\Galley;

class GalleyForm extends FormComponent
{
    public $id = 'galley';

    public $method = 'POST';

    public Context $context;
    public ?Galley $galley = null;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, Context $context, ?Galley $galley = null)
    {
        $this->action = $action;
        $this->context = $context;
        $this->galley = null;

        if ($this->galley) {
            $this->method = 'PUT';
        }

        $this
            ->addLabel()
            ->addLanguage()
            ->addIsRemote()
            ->addFile()
            ->addUrlPath()
            ->addUrlRemote();
    }

    protected function addLabel(): self
    {
        return $this->addField(new FieldText('label', [
            'label' => __('submission.layout.galleyLabel'),
            'description' => __('submission.layout.galleyLabelInstructions'),
            'value' => $this->galley ? $this->galley->getData('label') : '',
            'isRequired' => true,
        ]));
    }

    protected function addLanguage(): self
    {
        $languages = $this->context->getSupportedSubmissionLocaleNames();

        if (count($languages) < 2) {
            return $this;
        }

        $options = [];
        foreach ($languages as $locale => $name) {
            $options[] = [
                'value' => $locale,
                'label' => $name,
            ];
        }

        return $this->addField(new FieldSelect('locale', [
            'label' => __('common.language'),
            'options' => $options,
            'value' => $this->galley ? $this->galley->getData('locale') : $options[0]['value'],
            'isRequired' => true,
        ]));
    }

    protected function addIsRemote(): self
    {
        return $this->addField(new FieldOptions('isRemote', [
            'label' => __('isremote.text'),
            'description' => __('isremote.desc'),
            'type' => 'radio',
            'options' => [
                [
                    'value' => false,
                    'label' => __('local.text'),
                ],
                [
                    'value' => true,
                    'label' => __('submission.layout.galley.remotelyHostedContent'),
                ],
            ],
            'value' => $this->galley ? !empty($this->galley->getData('urlRemote')) : false,
            'isRequired' => true,
        ]));
    }

    protected function addFile(): self
    {
        return $this->addField(new FieldUpload('temporaryFileId', [
            'label' => __('field.upload'),
            'value' => '', // TEMPORARY
            'options' => [
                'url' => Application::get()->getDispatcher()->url(
                    Application::get()->getRequest(),
                    Application::ROUTE_API,
                    $this->context->getPath(),
                    'temporaryFiles',
                ),
            ],
            'showWhen' => ['isRemote', false],
        ]));
    }

    protected function addUrlPath(): self
    {
        return $this->addField(new FieldText('urlPath', [
            'label' => __('publication.urlPath'),
            'description' => __('publication.urlPath.description'),
            'value' => $this->galley ? $this->galley->getData('urlPath') : '',
            'showWhen' => ['isRemote', false],
        ]));
    }

    protected function addUrlRemote(): self
    {
        return $this->addField(new FieldText('urlRemote', [
            'label' => __('publication.urlRemote'),
            'description' => __('publication.urlRemote.description'),
            'value' => $this->galley ? $this->galley->getData('urlRemote') : '',
            'showWhen' => ['isRemote', true],
        ]));
    }
}
