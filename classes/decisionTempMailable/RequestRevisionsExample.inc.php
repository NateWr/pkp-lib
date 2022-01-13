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

class RequestRevisionsExample
{
    public $defaultEmailTemplateKey = 'EDITOR_DECISION_REVISIONS';

    /**
     * This is a dummy method. In practice, the email templates
     * attached to a mailable would have to ve retrieved from the database.
     */
    public function getEmailTemplates()
    {
        return [
            [
                'key' => 'EDITOR_DECISION_REVISIONS',
                'subject' => [
                    'en_US' => 'Request Revisions',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>...',
                ],
                'isDefault' => true,
            ],
            [
                'key' => 'REVISIONS_RESUBMIT',
                'subject' => [
                    'en_US' => 'Revise and Resubmit',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>...',
                ],
                'isDefault' => false,
            ],
            [
                'key' => 'REVISIONS_EDITOR_ONLY',
                'subject' => [
                    'en_US' => 'Request Revisions (Editor Only)',
                ],
                'body' => [
                    'en_US' => 'Dear {$recipientName},<br><br>...',
                ],
                'isDefault' => false,
            ]
        ];
    }
}
