<?php

/**
 * @file controllers/grid/settings/sections/form/PKPSectionForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSectionForm
 * @ingroup controllers_grid_settings_section_form
 *
 * @brief Form for adding/editing a section
 */

import('lib.pkp.classes.form.Form');

class PKPSectionForm extends Form {
	/** the id for the section being edited **/
	var $_sectionId;

	/** @var int The current user ID */
	var $_userId;

	/** @var string Cover image extension */
	var $_imageExtension;

	/** @var array Cover image information from getimagesize */
	var $_sizeArray;

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $template string Template path
	 * @param $sectionId int optional
	 */
	function __construct($request, $template, $sectionId = null) {
		$this->setSectionId($sectionId);

		$user = $request->getUser();
		$this->_userId = $user->getId();

		parent::__construct($template);

		// Validation checks for this form
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_PKP_MANAGER);
	}

	/**
	 * @copydoc Form::initData()
	 */
	public function initData() {

		$sectionId = $this->getSectionId();
		if (!$sectionId) {
			return;
		}

		$context = Application::get()->getRequest()->getContext();
		$section = DAORegistry::getDAO('SectionDAO')->getById($sectionId, $context->getId());

		$checks = [];
		$checkPlugins = PluginRegistry::loadCategory('checks', true);
		foreach ($checkPlugins as $checkPlugin) {
			if ($checkPlugin->getSetting($context->getId(), 'section-' . $section->getId())) {
				$checks[] = $checkPlugin->getName();
			}
		}

		$this->setData('checks', $checks);

		parent::initData();
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		$this->readUserVars(array('title', 'subEditors', 'checks'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {

		$checkOptions = [];
		$checkPlugins = PluginRegistry::loadCategory('checks', true);
		foreach ($checkPlugins as $checkPlugin) {
			$checkOptions[] = [
				'name' => $checkPlugin->getName(),
				'isEnabled' => empty($this->getData('checks')) || in_array($checkPlugin->getName(), $this->getData('checks')),
				'description' => $checkPlugin->getDescription(),
			];
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'checkOptions' => $checkOptions,
		]);

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute
	 */
	function execute() {
		if (!$this->getSectionId()) {
			return;
		}

		$context = Application::get()->getRequest()->getContext();
		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$section = $sectionDao->getById($this->getSectionId(), $context->getId());

		$checks = $this->getData('checks');
		$checkPlugins = PluginRegistry::loadCategory('checks', true);
		foreach ($checkPlugins as $checkPlugin) {
			$checkPlugin->updateSetting($context->getId(), 'section-' . $section->getId(), in_array($checkPlugin->getName(), $checks));
		}
	}

	/**
	 * Get the section ID for this section.
	 * @return int
	 */
	function getSectionId() {
		return $this->_sectionId;
	}

	/**
	 * Set the section ID for this section.
	 * @param $sectionId int
	 */
	function setSectionId($sectionId) {
		$this->_sectionId = $sectionId;
	}

	/**
	 * Get a list of all subeditor IDs assigned to this section
	 *
	 * @param $sectionId int
	 * @param $contextId int
	 * @return array
	 */
	public function _getAssignedSubEditorIds($sectionId, $contextId) {
		import('classes.core.Services');
		$subEditors = Services::get('user')->getMany(array(
			'contextId' => $contextId,
			'roleIds' => ROLE_ID_SUB_EDITOR,
			'assignedToSection' => $sectionId,
		));

		if (empty($subEditors)) {
			return array();
		}

		$subEditorIds = array_map(function($subEditor) {
			return (int) $subEditor->getId();
		}, $subEditors);

		return $subEditorIds;
	}

	/**
	 * Compile data for a subeditors SelectListPanel
	 *
	 * @param $contextId int
	 * @param $request Request
	 * @return \PKP\components\listPanels\ListPanel
	 */
	public function _getSubEditorsListPanel($contextId, $request) {

		$params = [
			'contextId' => $contextId,
			'roleIds' => ROLE_ID_SUB_EDITOR,
		];

		import('classes.core.Services');
		$userService = Services::get('user');
		$users = $userService->getMany($params);
		$items = [];
		foreach ($users as $user) {
			$items[] = [
				'id' => (int) $user->getId(),
				'title' => $user->getFullName()
			];
		}

		return new \PKP\components\listPanels\ListPanel(
			'subeditors',
			__('user.role.subEditors'),
			[
				'canSelect' => true,
				'getParams' => $params,
				'items' => $items,
				'itemsmax' => $userService->getMax($params),
				'selected' => $this->getData('subEditors')
						? $this->getData('subEditors')
						: [],
				'selectorName' => 'subEditors[]',
			]
		);
	}

	/**
	 * Save changes to subeditors
	 *
	 * @param $contextId int
	 */
	public function _saveSubEditors($contextId) {
		$subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
		$subEditorsDao->deleteBySectionId($this->getSectionId(), $contextId);
		$subEditors = $this->getData('subEditors');
		if (!empty($subEditors)) {
			$roleDao = DAORegistry::getDAO('RoleDAO');
			foreach ($subEditors as $subEditor) {
				if ($roleDao->userHasRole($contextId, $subEditor, ROLE_ID_SUB_EDITOR)) {
					$subEditorsDao->insertEditor($contextId, $this->getSectionId(), $subEditor);
				}
			}
		}
	}

}
