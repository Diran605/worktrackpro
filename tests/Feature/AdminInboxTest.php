<?php

namespace Tests\Feature;

use App\Filament\Resources\Inbox\MessageResource;
use App\Filament\Resources\Inbox\Pages\ListMessages;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageRecipient;
use App\Http\Controllers\InboxAttachmentController;
use App\Services\InboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function send($from, array $to, string $subject): Message
    {
        return app(InboxService::class)->sendMessage(
            organisationId: (int) ($from?->organisation_id ?? $to[0]->organisation_id),
            senderId: $from?->id,
            recipientIds: array_map(fn ($u) => $u->id, $to),
            subject: $subject,
            body: "Body of {$subject}",
        );
    }

    private function readAt(Message $message, $user)
    {
        return MessageRecipient::where('message_id', $message->id)->where('recipient_id', $user->id)->value('read_at');
    }

    public function test_the_inbox_only_shows_your_own_messages(): void
    {
        $admin = $this->makeUser('admin');
        $a = $this->makeUser('worker', $admin->organisation);
        $b = $this->makeUser('worker', $admin->organisation);

        $this->send($a, [$admin], 'For the admin');
        $this->send($a, [$b], 'Private between workers');

        $this->actingAs($admin);
        $this->get(MessageResource::getUrl('index'))
            ->assertOk()
            ->assertSee('For the admin')
            ->assertDontSee('Private between workers');
    }

    public function test_opening_a_message_marks_it_read_and_it_can_be_marked_unread(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $message = $this->send($worker, [$admin], 'Question');
        $this->actingAs($admin);

        Livewire::test(ListMessages::class)
            ->callTableAction('open', $message)
            ->assertHasNoTableActionErrors();
        $this->assertNotNull($this->readAt($message, $admin));

        Livewire::test(ListMessages::class)
            ->callTableAction('markUnread', $message)
            ->assertHasNoTableActionErrors();
        $this->assertNull($this->readAt($message, $admin));
    }

    public function test_mark_selected_and_mark_all_as_read(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $one = $this->send($worker, [$admin], 'One');
        $two = $this->send($worker, [$admin], 'Two');
        $three = $this->send(null, [$admin], 'Three');
        $this->actingAs($admin);

        Livewire::test(ListMessages::class)
            ->callTableBulkAction('markSelectedRead', [$one, $two]);
        $this->assertNotNull($this->readAt($one, $admin));
        $this->assertNull($this->readAt($three, $admin));
        $this->assertSame('1', MessageResource::getNavigationBadge());

        Livewire::test(ListMessages::class)->callAction('markAllRead');
        $this->assertNull(MessageResource::getNavigationBadge());
    }

    public function test_reply_goes_to_the_sender(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $message = $this->send($worker, [$admin], 'Leave request');
        $this->actingAs($admin);

        Livewire::test(ListMessages::class)
            ->callTableAction('reply', $message, data: ['subject' => 'Re: Leave request', 'body' => 'Approved.'])
            ->assertHasNoTableActionErrors();

        $reply = Message::where('subject', 'Re: Leave request')->firstOrFail();
        $this->assertSame($admin->id, $reply->sender_id);
        $this->assertTrue(MessageRecipient::where('message_id', $reply->id)->where('recipient_id', $worker->id)->exists());
    }

    public function test_attachments_download_through_signed_links_only(): void
    {
        Storage::fake('local');
        Storage::put('inbox_attachments/report.pdf', '%PDF-1.4');
        $admin = $this->makeUser('admin');
        $message = $this->send($this->makeUser('worker', $admin->organisation), [$admin], 'With file');
        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => 'inbox_attachments/report.pdf',
            'file_name' => 'report.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 8,
        ]);

        $this->get(InboxAttachmentController::signedUrl($attachment))->assertOk();
        $this->get("/inbox/attachments/{$attachment->id}")->assertForbidden();
    }
}
