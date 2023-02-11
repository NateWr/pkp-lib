<?php

/**
 * @file controllers/review/linkAction/ReviewNotesLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewInfoCenterLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to open up the review notes for a review assignments.
 */

namespace PKP\controllers\review\linkAction;

use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

class ReviewNotesLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment The review assignment
     * to show information about.
     * @param Submission $submission The reviewed submission.
     * @param User $user The user.
     * @param string $handler name of the gridhandler.
     * @param bool $isUnread Has a review been read
     */
    public function __construct($request, $reviewAssignment, $submission, $user, $handler, $isUnread = null)
    {
        // Instantiate the information center modal.
        $router = $request->getRouter();
        $actionArgs = [
            'submissionId' => $reviewAssignment->getSubmissionId(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
            'stageId' => $reviewAssignment->getStageId()
        ];

        $ajaxModal = new AjaxModal(
            $router->url(
                $request,
                null,
                $handler,
                'readReview',
                null,
                $actionArgs
            ),
            __('editor.review') . ': ' . htmlspecialchars($submission->getCurrentPublication()->getLocalizedTitle(null, 'html')),
            'modal_information'
        );

        $viewsDao = DAORegistry::getDAO('ViewsDAO'); /** @var ViewsDAO $viewsDao */
        $lastViewDate = $viewsDao->getLastViewDate(ASSOC_TYPE_REVIEW_RESPONSE, $reviewAssignment->getId(), $user->getId());

        $icon = !$lastViewDate || $isUnread ? 'read_new_review' : null;

        // Configure the link action.
        parent::__construct('readReview', $ajaxModal, __('editor.review.readReview'), $icon);
    }
}
