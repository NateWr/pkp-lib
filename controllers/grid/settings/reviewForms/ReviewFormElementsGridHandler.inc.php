<?php

/**
 * @file controllers/grid/settings/reviewForms/ReviewFormElementsGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementsGridHandler
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Handle review form element grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.settings.reviewForms.ReviewFormElementGridRow');
import('lib.pkp.controllers.grid.settings.reviewForms.form.ReviewFormElementForm');

class ReviewFormElementsGridHandler extends GridHandler
{
    /** @var int Review form ID */
    public $reviewFormId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                ROLE_ID_MANAGER],
            ['fetchGrid', 'fetchRow', 'saveSequence',
                'createReviewFormElement', 'editReviewFormElement', 'deleteReviewFormElement', 'updateReviewFormElement']
        );
    }

    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @see PKPHandler::authorize()
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

        $this->reviewFormId = (int) $request->getUserVar('reviewFormId');
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        if (!$reviewFormDao->reviewFormExists($this->reviewFormId, Application::getContextAssocType(), $request->getContext()->getId())) {
            return false;
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Load user-related translations.
        AppLocale::requireComponents(
            LOCALE_COMPONENT_APP_ADMIN,
            LOCALE_COMPONENT_APP_MANAGER,
            LOCALE_COMPONENT_APP_COMMON,
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_PKP_USER
        );

        // Grid actions.
        $router = $request->getRouter();

        import('lib.pkp.classes.linkAction.request.AjaxModal');

        // Create Review Form Element link
        $this->addAction(
            new LinkAction(
                'createReviewFormElement',
                new AjaxModal(
                    $router->url($request, null, null, 'createReviewFormElement', null, ['reviewFormId' => $this->reviewFormId]),
                    __('manager.reviewFormElements.create'),
                    'modal_add_item',
                    true
                ),
                __('manager.reviewFormElements.create'),
                'add_item'
            )
        );


        //
        // Grid columns.
        //
        import('lib.pkp.controllers.grid.settings.reviewForms.ReviewFormElementGridCellProvider');
        $reviewFormElementGridCellProvider = new ReviewFormElementGridCellProvider();

        // Review form element name.
        $this->addColumn(
            new GridColumn(
                'question',
                'manager.reviewFormElements.question',
                null,
                null,
                $reviewFormElementGridCellProvider,
                ['html' => true, 'maxLength' => 220]
            )
        );

        // Basic grid configuration.
        $this->setTitle('manager.reviewFormElements');
    }

    //
    // Implement methods from GridHandler.
    //
    /**
     * @see GridHandler::addFeatures()
     */
    public function initFeatures($request, $args)
    {
        import('lib.pkp.classes.controllers.grid.feature.OrderGridItemsFeature');
        return [new OrderGridItemsFeature()];
    }

    /**
     * @see GridHandler::getRowInstance()
     *
     * @return UserGridRow
     */
    protected function getRowInstance()
    {
        return new ReviewFormElementGridRow();
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        // Get review form elements.
        //$rangeInfo = $this->getRangeInfo('reviewFormElements');
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElements = $reviewFormElementDao->getByReviewFormId($this->reviewFormId, null); //FIXME add range info?

        return $reviewFormElements->toAssociativeArray();
    }

    /**
     * @copydoc CategoryGridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        return array_merge(['reviewFormId' => $this->reviewFormId], parent::getRequestArgs());
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($gridDataElement)
    {
        return $gridDataElement->getSequence();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $gridDataElement->setSequence($newSequence);
        $reviewFormElementDao->updateObject($gridDataElement);
    }


    //
    // Public grid actions.
    //
    /**
     * Add a new review form element.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function createReviewFormElement($args, $request)
    {
        // Form handling
        $reviewFormElementForm = new ReviewFormElementForm($this->reviewFormId);
        $reviewFormElementForm->initData();
        return new JSONMessage(true, $reviewFormElementForm->fetch($request));
    }

    /**
     * Edit an existing review form element.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function editReviewFormElement($args, $request)
    {
        // Identify the review form element Id
        $reviewFormElementId = (int) $request->getUserVar('rowId');

        // Display form
        $reviewFormElementForm = new ReviewFormElementForm($this->reviewFormId, $reviewFormElementId);
        $reviewFormElementForm->initData();
        return new JSONMessage(true, $reviewFormElementForm->fetch($request));
    }

    /**
     * Save changes to a review form element.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function updateReviewFormElement($args, $request)
    {
        $reviewFormElementId = (int) $request->getUserVar('reviewFormElementId');

        $context = $request->getContext();
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */

        $reviewForm = $reviewFormDao->getById($this->reviewFormId, Application::getContextAssocType(), $context->getId());

        if (!$reviewFormDao->unusedReviewFormExists($this->reviewFormId, Application::getContextAssocType(), $context->getId()) || ($reviewFormElementId && !$reviewFormElementDao->reviewFormElementExists($reviewFormElementId, $this->reviewFormId))) {
            fatalError('Invalid review form information!');
        }

        import('lib.pkp.controllers.grid.settings.reviewForms.form.ReviewFormElementForm');
        $reviewFormElementForm = new ReviewFormElementForm($this->reviewFormId, $reviewFormElementId);
        $reviewFormElementForm->readInputData();

        if ($reviewFormElementForm->validate()) {
            $reviewFormElementId = $reviewFormElementForm->execute();

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return DAO::getDataChangedEvent($reviewFormElementId);
        }

        return new JSONMessage(false);
    }

    /**
     * Delete a review form element.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function deleteReviewFormElement($args, $request)
    {
        $reviewFormElementId = (int) $request->getUserVar('rowId');

        $context = $request->getContext();
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */

        if ($request->checkCSRF() && $reviewFormDao->unusedReviewFormExists($this->reviewFormId, Application::getContextAssocType(), $context->getId())) {
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElementDao->deleteById($reviewFormElementId);
            return DAO::getDataChangedEvent($reviewFormElementId);
        }

        return new JSONMessage(false);
    }
}
