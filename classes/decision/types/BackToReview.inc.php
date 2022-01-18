<?php
/**
 * @file classes/decision/types/BackToReview.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A decision to return a submission to the external review stage.
 */

namespace PKP\decision\types;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Validation\Validator;
use PKP\components\fileAttachers\FileStage;
use PKP\components\fileAttachers\Library;
use PKP\components\fileAttachers\Upload;
use PKP\context\Context;
use PKP\decision\steps\Email;
use PKP\decision\Type;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\decision\Workflow;
use PKP\mail\mailables\DecisionBackToReviewNotifyAuthor;
use PKP\security\Role;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class BackToReview extends Type
{
    use NotifyAuthors;

    public function getDecision(): int
    {
        return Decision::BACK_TO_REVIEW;
    }

    public function getStageId(): int
    {
        return WORKFLOW_STAGE_ID_EDITING;
    }

    public function getNewStageId(): int
    {
        return WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
    }

    public function getNewStatus(): ?int
    {
        return null;
    }

    public function getNewReviewRoundStatus(): ?int
    {
        return null;
    }

    public function getLabel(?string $locale = null): string
    {
        return __('editor.submission.decision.backToReview', [], $locale);
    }

    public function getDescription(?string $locale = null): string
    {
        return __('editor.submission.decision.backToReview.description', [], $locale);
    }

    public function getLog(): string
    {
        return 'editor.submission.decision.backToReview.log';
    }

    public function getCompletedLabel(): string
    {
        return __('editor.submission.decision.backToReview.completed');
    }

    public function getCompletedMessage(Submission $submission): string
    {
        return __('editor.submission.decision.backToReview.completed.description', ['title' => $submission->getLocalizedFullTitle()]);
    }

    public function validate(array $props, Submission $submission, Context $context, Validator $validator, ?int $reviewRoundId = null)
    {
        parent::validate($props, $submission, $context, $validator, $reviewRoundId);

        foreach ($props['actions'] as $index => $action) {
            $actionErrorKey = 'actions.' . $index;
            switch ($action['id']) {
                case $this->ACTION_NOTIFY_AUTHORS:
                    $this->validateNotifyAuthorsAction($action, $actionErrorKey, $validator, $submission);
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
                    $this->sendAuthorEmail(
                        new DecisionBackToReviewNotifyAuthor($context, $submission, $decision),
                        $this->getEmailDataFromAction($action),
                        $editor,
                        $submission,
                        $context
                    );
                    break;
            }
        }
    }

    public function getWorkflow(Submission $submission, Context $context, User $editor, ?ReviewRound $reviewRound): Workflow
    {
        $workflow = new Workflow($this, $submission, $context);

        $fakeDecision = $this->getFakeDecision($submission, $editor);
        $fileAttachers = $this->getFileAttachers($submission, $context);

        $authors = $workflow->getStageParticipants(Role::ROLE_ID_AUTHOR);
        if (count($authors)) {
            $mailable = new DecisionBackToReviewNotifyAuthor($context, $submission, $fakeDecision);
            $workflow->addStep(new Email(
                $this->ACTION_NOTIFY_AUTHORS,
                __('editor.submission.decision.notifyAuthors'),
                __('editor.submission.decision.backToReview.notifyAuthorsDescription'),
                $authors,
                $mailable
                    ->sender($editor)
                    ->recipients($authors),
                $context->getSupportedFormLocales(),
                $fileAttachers
            ));
        }

        return $workflow;
    }

    /**
     * Get the submission file stages that are permitted to be attached to emails
     * sent in this decision
     *
     * @return array<int>
     */
    protected function getAllowedAttachmentFileStages(): array
    {
        return [
            SubmissionFile::SUBMISSION_FILE_FINAL,
        ];
    }

    /**
     * Get the file attacher components supported for emails in this decision
     */
    protected function getFileAttachers(Submission $submission, Context $context): array
    {
        $attachers = [
            new Upload(
                $context,
                __('common.upload.addFile'),
                __('common.upload.addFile.description'),
                __('common.upload.addFile')
            ),
        ];

        $attachers[] = (new FileStage(
            $context,
            $submission,
            __('submission.submit.submissionFiles'),
            __('email.addAttachment.submissionFiles.submissionDescription'),
            __('email.addAttachment.submissionFiles.attach')
        ))
            ->withFileStage(
                SubmissionFile::SUBMISSION_FILE_FINAL,
                __('submission.finalDraft')
            );

        $attachers[] = new Library(
            $context,
            $submission
        );

        return $attachers;
    }
}