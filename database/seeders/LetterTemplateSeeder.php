<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

class LetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'letter_type' => 'appointment',
                'name' => 'Appointment Letter',
                'subject_template' => 'Appointment — {{worker_name}}',
                'body_template' => '<p>Dear {{worker_name}},</p><p>We are pleased to confirm your appointment.</p><p>Date: {{date}}</p><p>Regards,<br>{{admin_name}}</p>',
                'requires_acknowledgement' => false,
            ],
            [
                'letter_type' => 'warning',
                'name' => 'Warning Letter',
                'subject_template' => 'Warning — {{worker_name}}',
                'body_template' => '<p>Dear {{worker_name}},</p><p>This letter serves as a formal warning.</p><p>Date: {{date}}</p><p>Regards,<br>{{admin_name}}</p>',
                'requires_acknowledgement' => true,
            ],
            [
                'letter_type' => 'query',
                'name' => 'Query Letter',
                'subject_template' => 'Query — {{worker_name}}',
                'body_template' => '<p>Dear {{worker_name}},</p><p>You are requested to provide clarification regarding the matter below.</p><p>Date: {{date}}</p><p>Regards,<br>{{admin_name}}</p>',
                'requires_acknowledgement' => true,
            ],
            [
                'letter_type' => 'confirmation',
                'name' => 'Confirmation Letter',
                'subject_template' => 'Confirmation — {{worker_name}}',
                'body_template' => '<p>Dear {{worker_name}},</p><p>This is to confirm the referenced action.</p><p>Date: {{date}}</p><p>Regards,<br>{{admin_name}}</p>',
                'requires_acknowledgement' => false,
            ],
            [
                'letter_type' => 'custom',
                'name' => 'Custom Letter (Base)',
                'subject_template' => 'Letter — {{worker_name}}',
                'body_template' => '<p>Dear {{worker_name}},</p><p>{{custom_field_1}}</p><p>Date: {{date}}</p><p>Regards,<br>{{admin_name}}</p>',
                'requires_acknowledgement' => false,
            ],
        ];

        foreach ($defaults as $d) {
            LetterTemplate::firstOrCreate(
                [
                    'organisation_id' => null,
                    'letter_type' => $d['letter_type'],
                    'name' => $d['name'],
                ],
                [
                    'subject_template' => $d['subject_template'],
                    'body_template' => $d['body_template'],
                    'is_system_default' => true,
                    'requires_acknowledgement' => $d['requires_acknowledgement'],
                    'created_by' => null,
                    'last_edited_by' => null,
                ]
            );
        }
    }
}

