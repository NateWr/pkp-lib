<?php

/**
 * @file controllers/grid/navigationMenus/NavigationMenuItemsGridRow.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsGridRow
 * @ingroup controllers_grid_navigationMenus
 *
 * @brief NavigationMenuItem grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class NavigationMenuItemsGridRow extends GridRow {
	/** @var int the ID of the parent navigationMenuId */
	var $navigationMenuIdParent;

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		// Is this a new row or an existing row?
		$element = $this->getData();
		assert(is_a($element, 'NavigationMenuItem'));

		$rowId = $this->getId();

		if ($request->getUserVar('rowId')){
			$this->navigationMenuIdParent = $request->getUserVar('rowId')['parentElementId'];
		} else {
			$this->navigationMenuIdParent = $request->getUserVar('navigationMenuIdParent');
		}

		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router = $request->getRouter();
			$actionArgs = array(
				'navigationMenuItemId' => $rowId,
				'navigationMenuIdParent' => $this->navigationMenuIdParent
			);
			$this->addAction(
				new LinkAction(
					'edit',
					new AjaxModal(
						$router->url($request, null, null, 'editNavigationMenuItem', null, $actionArgs),
						__('grid.action.edit'),
						'modal_edit',
						true
						),
					__('grid.action.edit'),
					'edit')
			);
			$this->addAction(
				new LinkAction(
					'remove',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('common.confirmDelete'),
						__('common.remove'),
						$router->url($request, null, null, 'deleteNavigationMenuItem', null, $actionArgs),
						'modal_delete'
						),
					__('grid.action.remove'),
					'delete')
			);
		}
	}
}

?>
