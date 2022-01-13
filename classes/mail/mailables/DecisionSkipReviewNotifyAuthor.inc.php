<?php

/**
 * @file classes/mail/mailables/DecisionSkipReviewNotifyAuthor.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionSkipReviewNotifyAuthor
 *
 * @brief Email sent to the author(s) when a SUBMISSION_EDITOR_DECISION_SKIP_REVIEW
 *  decision is made.
 */

namespace PKP\mail\mailables;

use APP\decision\Decision;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;

class DecisionSkipReviewNotifyAuthor extends Mailable
{
    use Recipient;
    use Sender;

    public $defaultEmailTemplateKey = 'EDITOR_DECISION_SKIP_REVIEW';

    protected static ?string $name = 'mailable.decision.skipReview.notifyAuthor.name';

    protected static ?string $description = 'mailable.decision.skipReview.notifyAuthor.description';

    public static bool $supportsTemplates = true;

    protected static array $groupIds = [self::GROUP_SUBMISSION];

    public function __construct(Context $context, Submission $submission, Decision $decision)
    {
        parent::__construct(func_get_args());
    }
}
