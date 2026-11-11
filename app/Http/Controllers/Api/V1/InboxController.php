<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Services\InboxService;
use App\Services\OrganisationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboxController extends Controller
{
    public function __construct(private readonly InboxService $inboxService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Message::query()
            ->select('messages.*', 'message_recipients.read_at as recipient_read_at')
            ->join('message_recipients', 'message_recipients.message_id', '=', 'messages.id')
            ->where('message_recipients.recipient_id', $user->id)
            ->with(['sender:id,name', 'attachments:id,message_id,file_name,file_type,file_size'])
            ->orderByDesc('messages.created_at');

        $messages = $query->paginate(20);

        return response()->json($messages);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $recipient = MessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->where('message_id', $id)
            ->firstOrFail();

        if (!$recipient->read_at) {
            $recipient->update(['read_at' => now()]);
        }

        $message = Message::query()
            ->with(['sender:id,name', 'attachments'])
            ->findOrFail($id);

        return response()->json(['data' => $message, 'read_at' => $recipient->read_at]);
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = app(OrganisationSettingsService::class)->forOrganisation((int) $user->organisation_id);
        $maxKb = (int) ($settings->inbox_max_attachment_kb ?: 5120);

        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments.*' => ['file', 'max:' . $maxKb],
        ]);

        $allowed = $settings->inbox_allowed_mime_types ?: null;
        if ($allowed && $request->hasFile('attachments')) {
            foreach ($request->file('attachments', []) as $file) {
                if (!$file) continue;
                $mime = (string) $file->getClientMimeType();
                if (!in_array($mime, $allowed, true)) {
                    return response()->json([
                        'message' => 'Attachment type not allowed.',
                    ], 422);
                }
            }
        }

        // Ensure same organisation
        $recipientOrg = DB::table('users')->where('id', $validated['recipient_id'])->value('organisation_id');
        if ((int) $recipientOrg !== (int) $user->organisation_id) {
            abort(403);
        }

        $message = $this->inboxService->sendMessage(
            organisationId: (int) $user->organisation_id,
            senderId: (int) $user->id,
            recipientIds: [(int) $validated['recipient_id']],
            subject: $validated['subject'],
            body: $validated['body'],
            messageType: 'direct',
            attachments: $request->file('attachments', [])
        );

        return response()->json(['data' => $message], 201);
    }

    public function downloadAttachment(Request $request, int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $attachment = \App\Models\MessageAttachment::query()
            ->whereHas('message.recipients', function ($q) use ($user) {
                $q->where('recipient_id', $user->id);
            })
            ->findOrFail($id);

        if (!\Illuminate\Support\Facades\Storage::exists($attachment->file_path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::download($attachment->file_path, $attachment->file_name);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = MessageRecipient::query()
            ->where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }
    public function requestReopenLatest(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        // Find the latest closed/system_closed session for this worker
        $session = \App\Models\WorkSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                \App\Enums\SessionStatus::Closed->value,
                \App\Enums\SessionStatus::SystemClosed->value,
            ])
            ->orderByDesc('date')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'No closed session found to reopen.',
            ], 422);
        }

        // Check if there's already a pending request for this session
        $existing = \App\Models\SessionReopenRequest::query()
            ->where('work_session_id', $session->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A reopen request is already pending for this session.',
                'data' => $existing,
            ]);
        }

        $reopen = \App\Models\SessionReopenRequest::create([
            'work_session_id' => $session->id,
            'requested_by' => $user->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json(['data' => $reopen], 201);
    }
}

