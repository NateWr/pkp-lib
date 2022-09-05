<?php

/**
 * @file classes/mail/mailables/SubmissionAcknowledgementOtherAuthors.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAcknowledgementOtherAuthors
 *
 * @brief Email sent to authors named as contributors to a new submission who
 *   are not the submitting author.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;
use PKP\user\User;

class SubmissionAcknowledgementOtherAuthors extends Mailable
{
    use Recipient;

    protected const SUBMITTER_NAME = 'submitterName';

    protected static ?string $name = 'mailable.submissionAckOtherAuthors.name';
    protected static ?string $description = 'emails.submissionAckNotUser.description';
    protected static ?string $emailTemplateKey = 'SUBMISSION_ACK_NOT_USER';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    protected Decision $decision;
    protected Enumerable $submitterUsers;


    public function __construct(Context $context, Submission $submission, Enumerable $submitterUsers)
    {
        parent::__construct([$context, $submission]);
        $this->submitterUsers = $submitterUsers;
        $this->addSubmitterName();
    }

    public function addSubmitterName(): void
    {
        $this->addData([
            self::SUBMITTER_NAME => $this->submitterUsers
                ->map(fn (User $user) => $user->getFullName())
                ->join(__('common.commaListSeparator')),
        ]);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge([
            parent::getDataDescriptions(),
            [
                self::SUBMITTER_NAME => __('emailTemplate.variable.submitterName'),
            ]
        ]);
    }
}
