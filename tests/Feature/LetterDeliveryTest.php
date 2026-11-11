<?php

namespace Tests\Feature;

use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Models\Message;
use App\Services\LetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LetterDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    private function issueLetter(): array
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $template = LetterTemplate::create([
            'organisation_id' => $admin->organisation_id,
            'letter_type' => 'warning',
            'name' => 'Warning',
            'subject_template' => 'Warning',
            'body_template' => '<p>Body</p>',
            'requires_acknowledgement' => true,
        ]);

        $letter = app(LetterService::class)->generateAndDeliver($template, $worker, $admin, 'Formal warning', '<p>Please read.</p>');

        return [$letter, $worker];
    }

    public function test_letter_message_is_linked_and_acknowledgement_finds_the_letter(): void
    {
        [$letter, $worker] = $this->issueLetter();

        $message = Message::where('generated_letter_id', $letter->id)->firstOrFail();
        Storage::assertExists($letter->pdf_path);

        Sanctum::actingAs($worker);
        $this->postJson("/api/v1/inbox/{$message->id}/acknowledge")->assertOk();

        $this->assertNotNull($letter->fresh()->acknowledged_at);
    }

    public function test_acknowledging_a_resent_copy_works(): void
    {
        [$letter, $worker] = $this->issueLetter();

        $resent = app(LetterService::class)->deliver($letter, 'Resent');
        $this->assertSame(2, Message::where('generated_letter_id', $letter->id)->count());

        Sanctum::actingAs($worker);
        $this->postJson("/api/v1/inbox/{$resent->id}/acknowledge")->assertOk();

        $this->assertNotNull($letter->fresh()->acknowledged_at);
    }

    public function test_someone_else_cannot_acknowledge_a_letter(): void
    {
        [$letter, $worker] = $this->issueLetter();
        $message = Message::where('generated_letter_id', $letter->id)->first();

        Sanctum::actingAs($this->makeUser('worker', $worker->organisation));
        $this->postJson("/api/v1/inbox/{$message->id}/acknowledge")->assertNotFound();

        $this->assertNull($letter->fresh()->acknowledged_at);
    }

    public function test_failed_delivery_saves_nothing_and_removes_the_pdf(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $template = LetterTemplate::create([
            'organisation_id' => $admin->organisation_id,
            'letter_type' => 'warning',
            'name' => 'Warning',
            'subject_template' => 'Warning',
            'body_template' => '<p>Body</p>',
        ]);

        // Make the inbox step fail after the letter row has been written.
        Message::creating(fn () => throw new \RuntimeException('inbox down'));

        try {
            app(LetterService::class)->generateAndDeliver($template, $worker, $admin, 'Subject', '<p>x</p>');
            $this->fail('Expected the delivery to fail');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, GeneratedLetter::count());
        $this->assertSame([], Storage::allFiles('letters'));
    }
}
