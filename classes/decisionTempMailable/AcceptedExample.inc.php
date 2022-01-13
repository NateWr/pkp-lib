<?php
/**
 * DO NOT MERGE
 *
 * This is just a temporary example class to show how an editorial
 * decision type can use a mailable class to get information about
 * what email templates to use in the UI.
 *
 * It would be replaced by a real mailable class after Vitaly's
 * work is merged.
 */

namespace PKP\decisionTempMailable;

class AcceptedExample
{
    public static $defaultEmailTemplateKey = 'EDITOR_DECISION_ACCEPT';

    /**
     * This is a dummy method. In practice, the email templates
     * attached to a mailable would have to ve retrieved from the database.
     */
    public function getEmailTemplates()
    {
        return [
            [
                'key' => 'EDITOR_DECISION_ACCEPT',
                'subject' => [
                    'en_US' => 'Accepted',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>We are delighted to let you know that your submission, {$submissionTitle}, has been accepted for publication.<br><br>Sincerely, {$senderName}',
                ],
                'isDefault' => true,
            ],
            [
                'key' => 'ACCEPTED_CONDITIONAL',
                'subject' => [
                    'en_US' => 'Accepted With Conditions',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>We are ready to accept your submission, {$submissionTitle}, for publication. However, we have some conditions...<br><br>Sincerely, {$senderName}',
                ],
                'isDefault' => false,
            ],
            [
                'key' => 'ACCEPTED_EARLY_PUBLICATION',
                'subject' => [
                    'en_US' => 'Accepted for Early Publication',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>We are delighted to let you know that your submission, {$submissionTitle}, has been accepted for publication. We would like to prepare it for early publication. This means we would like to publish your recent revised version while it undergoes final copyediting.<br><br>Sincerely, {$senderName}',
                ],
                'isDefault' => false,
            ]
        ];
    }
}
