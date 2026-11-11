<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRecipient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InboxService
{
    public function sendMessage(
        int $organisationId,
        ?int $senderId,
        array $recipientIds,
        string $subject,
        string $body,
        string $messageType = 'direct',
        array $attachments = []
    ): Message {
        $message = Message::create([
            'organisation_id' => $organisationId,
            'sender_id' => $senderId,
            'subject' => $subject,
            'body' => $body,
            'message_type' => $messageType,
        ]);

        foreach (array_unique($recipientIds) as $rid) {
            MessageRecipient::create([
                'message_id' => $message->id,
                'recipient_id' => $rid,
            ]);
        }

        foreach ($attachments as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('inbox_attachments', ['disk' => config('filesystems.default')]);

            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => (string) $file->getClientMimeType(),
                'file_size' => (int) $file->getSize(),
            ]);
        }

        return $message->fresh(['sender', 'attachments', 'recipients']);
    }
}

