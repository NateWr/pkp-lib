<?php
/**
 * @file classes/components/listPanels/GalleysListPanel.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleysListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing email templates
 */

namespace PKP\components\listPanels;

use PKP\components\forms\galley\GalleyForm;
use PKP\context\Context;

class GalleysListPanel extends ListPanel
{
    public string $apiUrl;
    public Context $context;
    public GalleyForm $form;

    public function __construct(string $id, string $title, Context $context, string $apiUrl = '')
    {
        parent::__construct($id, $title, []);
        $this->context = $context;
        $this->apiUrl = $apiUrl;

        $this->form = new GalleyForm($apiUrl, $context);
    }

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();

        $config['apiUrl'] = $this->apiUrl;
        $config['form'] = $this->form->getConfig();

        $config['i18nAddGalley'] = __('grid.action.addGalley');
        $config['i18nCancelUpload'] = __('form.dropzone.dictCancelUpload');
        $config['i18nChangeFile'] = __('common.upload.changeFile');
        $config['i18nConfirmDelete'] = __('grid.action.confirmDeleteGalley');
        $config['i18nEditGalley'] = __('grid.action.editGalley');
        $config['i18nEditMetadata'] = __('submission.editMetadata');
        $config['i18nDeleteGalley'] = __('grid.action.deleteGalley');
        $config['i18nOrder'] = __('common.order');
        $config['i18nOrdering'] = __('common.ordering');
        $config['i18nSaveOrder'] = __('grid.action.saveOrdering');
        $config['i18nUploadProgress'] = __('submission.upload.percentComplete');

        return $config;
    }
}
