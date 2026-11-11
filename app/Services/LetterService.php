<?php

namespace App\Services;

use App\Models\CompanyLetterhead;
use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRecipient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class LetterService
{
    public function generateAndDeliver(LetterTemplate $template, User $worker, User $generatedBy, string $subject, string $bodyHtml): GeneratedLetter
    {
        $organisationId = (int) $generatedBy->organisation_id;

        $letterhead = CompanyLetterhead::query()
            ->where('organisation_id', $organisationId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        $headerImagePath = $letterhead?->header_image_path ? Storage::disk('public')->path($letterhead->header_image_path) : null;
        $footerImagePath = $letterhead?->footer_image_path ? Storage::disk('public')->path($letterhead->footer_image_path) : null;

        $pdf = Pdf::loadView('exports.letter', [
            'subject' => $subject,
            'body' => $bodyHtml,
            'companyName' => $letterhead?->company_name,
            'accentColor' => $letterhead?->accent_color ?? '#0d9488',
            'headerImagePath' => $headerImagePath,
            'footerImagePath' => $footerImagePath,
        ]);

        $pdfPath = 'letters/' . now()->format('Ymd_His') . '_worker_' . $worker->id . '.pdf';
        Storage::put($pdfPath, $pdf->output());

        $letter = GeneratedLetter::create([
            'organisation_id' => $organisationId,
            'worker_id' => $worker->id,
            'generated_by' => $generatedBy->id,
            'letter_template_id' => $template->id,
            'letter_type' => $template->letter_type,
            'subject' => $subject,
            'body_snapshot' => $bodyHtml,
            'pdf_path' => $pdfPath,
            'custom_fields' => null,
            'generated_at' => now(),
        ]);

        // Deliver via inbox
        $message = Message::create([
            'organisation_id' => $organisationId,
            'sender_id' => null,
            'subject' => $subject,
            'body' => 'A new letter has been issued to you. Please see the attached PDF.',
            'message_type' => 'letter',
        ]);

        MessageRecipient::create([
            'message_id' => $message->id,
            'recipient_id' => $worker->id,
        ]);

        MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $pdfPath,
            'file_name' => basename($pdfPath),
            'file_type' => 'application/pdf',
            'file_size' => (int) (Storage::size($pdfPath) ?? 0),
        ]);

        return $letter;
    }
}

