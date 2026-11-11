<?php

namespace App\Http\Controllers;

use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attachment downloads for the admin panel inbox. Links are signed and short-lived, and are only
 * generated for the message's sender or recipients.
 */
class InboxAttachmentController extends Controller
{
    public function download(MessageAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::exists($attachment->file_path), 404);

        return Storage::download($attachment->file_path, $attachment->file_name);
    }

    public static function signedUrl(MessageAttachment $attachment): string
    {
        return URL::temporarySignedRoute('inbox.attachment', now()->addMinutes(30), ['attachment' => $attachment->id]);
    }
}
