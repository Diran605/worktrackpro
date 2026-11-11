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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LetterService
{
    /**
     * Render the letter, record it and deliver it to the worker's inbox.
     * The database writes happen together: if any fails, nothing is saved and the PDF is removed.
     */
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

        // A random suffix avoids two letters generated in the same second overwriting each other.
        $pdfPath = 'letters/' . now()->format('Ymd_His') . '_worker_' . $worker->id . '_' . Str::random(6) . '.pdf';
        Storage::put($pdfPath, $pdf->output());

        try {
            return DB::transaction(function () use ($organisationId, $worker, $generatedBy, $template, $subject, $bodyHtml, $pdfPath) {
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

                $this->deliver($letter, 'A new letter has been issued to you. Please see the attached PDF.');

                return $letter;
            });
        } catch (Throwable $e) {
            Storage::delete($pdfPath);
            throw $e;
        }
    }

    /**
     * Send (or resend) a letter to the worker's inbox with its PDF attached.
     */
    public function deliver(GeneratedLetter $letter, string $body): Message
    {
        return DB::transaction(function () use ($letter, $body) {
            $message = Message::create([
                'organisation_id' => $letter->organisation_id,
                'sender_id' => null,
                'subject' => $letter->subject,
                'body' => $body,
                'message_type' => 'letter',
                'generated_letter_id' => $letter->id,
            ]);

            MessageRecipient::create([
                'message_id' => $message->id,
                'recipient_id' => $letter->worker_id,
            ]);

            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $letter->pdf_path,
                'file_name' => basename($letter->pdf_path),
                'file_type' => 'application/pdf',
                'file_size' => Storage::exists($letter->pdf_path) ? (int) Storage::size($letter->pdf_path) : 0,
            ]);

            return $message;
        });
    }
}
