<?php
/**
 * @file classes/decision/types/Resubmit.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to request the author submit revisions for another round of review.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\decision\steps\Email;
use PKP\decision\Type;
use PKP\decision\types\traits\InExternalReviewRound;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\types\traits\NotifyReviewers;
use PKP\decision\Workflow;
use PKP\mail\mailables\DecisionNotifyReviewer;
use PKP\mail\mailables\DecisionResubmitNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class Resubmit extends Type
{
    use InExternalReviewRound;
    use NotifyAuthors;
    use NotifyReviewers;

    public function getDecision(): int
    {
        return Decision::RESUBMIT;
    }

    public function getNewStageId(): ?int
    {
        return null;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.resubmit', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.resubmit.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.resubmit.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.resubmit.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.resubmit.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        // If there is no review round id, a validation error will already have been set
        if (!$reviewRoundId) {
            return;
        }

        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        foreach ($props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->validateNotifyReviewersAction($action, $actionErrorKey, $validator, $submission, $reviewRoundId);
                    break;
            }
        }
    }

    public function callback(Decision $decision, Submission $submission, User $editor, Context $context, array $actions)
    {
        parent::callback($decision, $submission, $editor, $context, $actions);

        foreach ($actions as $action) {
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $reviewAssignments = $this->getCompletedReviewAssignments($submission->getId(), $decision->getData('reviewRoundId'));
                    $emailData = $this->getEmailDataFromAction($action);
                    $this->sendAuthorEmail(
                        new DecisionResubmitNotifyAuthor($context, $submission, $decision, $reviewAssignments),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    $this->shareReviewAttachmentFiles($emailData->attachments, $submission, $decision->getData('reviewRoundId'));
                    break;
                case $this->ACTION_NOTIFY_REVIEWERS:
                    $this->sendReviewersEmail(
                        new DecisionNotifyReviewer($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission
                    );
                    break;
            }
        }
    }

    public function getWorkflow(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Workflow
    {
        $workflow = new Workflow($this, $submission, $context, $reviewRound);

        $fakeDecision = $this->getFakeDecision($submission, $editor, $reviewRound);
        $fileAttachers = $this->getFileAttachers($submission, $context, $reviewRound);
        $reviewAssignments = $this->getCompletedReviewAssignments($submission->getId(), $reviewRound->getId());

        $authors = $workflow->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionResubmitNotifyAuthor($context, $submission, $fakeDecision, $reviewAssignments);
            $workflow->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.requestRevisions.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        if (count($reviewAssignments)) {
            $reviewers = $workflow->getReviewersFromAssignments($reviewAssignments);
            $mailable = new DecisionNotifyReviewer($context, $submission, $fakeDecision);
            $workflow->addStep((new Email(
                $this->ACTION_NOTIFY_REVIEWERS,
                __('editor.submission.decision.notifyReviewers'),
                __('editor.submission.decision.notifyReviewers.description'),
                $reviewers,
                $mailable->sender($editor),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ))->canChangeTo(true));
        }

        return $workflow;
    }
}