<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GeneratedLetter;
use App\Models\MessageRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxAcknowledgeController extends Controller
{
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $recipient = MessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->where('message_id', $id)
            ->firstOrFail();

        $message = $recipient->message()->with('attachments')->first();

        $pdfPath = $message?->attachments?->first()?->file_path;

        if ($pdfPath) {
            $letter = GeneratedLetter::query()
                ->where('worker_id', $user->id)
                ->where('pdf_path', $pdfPath)
                ->first();

            if ($letter && !$letter->acknowledged_at) {
                $letter->update([
                    'acknowledged_at' => now(),
                    'acknowledged_by' => $user->id,
                ]);
            }
        }

        if (!$recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'Acknowledged.']);
    }
}

