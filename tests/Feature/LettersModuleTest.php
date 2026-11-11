<?php

namespace Tests\Feature;

use App\Filament\Pages\GenerateLetter;
use App\Filament\Resources\Letters\IssuedLetterResource;
use App\Filament\Resources\Letters\LetterTemplateResource;
use App\Filament\Resources\Letters\Pages\ListIssuedLetters;
use App\Filament\Resources\Letters\Pages\ListLetterTemplates;
use App\Models\CompanyLetterhead;
use App\Models\GeneratedLetter;
use App\Models\LetterTemplate;
use App\Models\Message;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class LettersModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(LetterTemplateSeeder::class);
        Storage::fake('local');
        $this->travelTo('2026-11-11 10:00:00');
    }

    public function test_admins_cannot_edit_shared_system_templates_but_can_copy_them(): void
    {
        $admin = $this->makeUser('admin');
        $system = LetterTemplate::where('letter_type', 'warning')->firstOrFail();
        $this->actingAs($admin);

        $this->assertFalse(LetterTemplateResource::canEdit($system));
        $this->assertFalse(LetterTemplateResource::canDelete($system));
        $this->get(LetterTemplateResource::getUrl('edit', ['record' => $system]))->assertForbidden();

        Livewire::test(ListLetterTemplates::class)->callTableAction('duplicate', $system);

        $copy = LetterTemplate::where('organisation_id', $admin->organisation_id)->firstOrFail();
        $this->assertSame('Warning Letter (copy)', $copy->name);
        $this->assertFalse($copy->is_system_default);
        $this->assertTrue($copy->requires_acknowledgement);
        $this->assertTrue(LetterTemplateResource::canEdit($copy));

        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(LetterTemplateResource::canEdit($system));
    }

    public function test_generating_fills_placeholders_safely_and_blocks_leftovers(): void
    {
        $admin = $this->makeUser('admin', null, null, ['name' => 'Ada Admin']);
        $worker = $this->makeUser('worker', $admin->organisation, null, ['name' => 'Bo <b>Bold</b>']);
        $warning = LetterTemplate::where('letter_type', 'warning')->firstOrFail();
        $custom = LetterTemplate::where('letter_type', 'custom')->firstOrFail();
        $this->actingAs($admin);

        // The "custom" template has {{custom_field_1}}, which must be replaced before sending
        Livewire::test(GenerateLetter::class)
            ->fillForm(['worker_id' => $worker->id, 'letter_template_id' => $custom->id])
            ->call('submit')
            ->assertHasErrors(['data.body']);
        $this->assertSame(0, GeneratedLetter::count());

        Livewire::test(GenerateLetter::class)
            ->fillForm(['worker_id' => $worker->id, 'letter_template_id' => $warning->id])
            ->assertFormSet(['subject' => 'Warning — Bo <b>Bold</b>'])
            ->call('submit')
            ->assertHasNoErrors();

        $letter = GeneratedLetter::firstOrFail();
        $this->assertSame($worker->id, $letter->worker_id);
        $this->assertStringContainsString('Bo &lt;b&gt;Bold&lt;/b&gt;', $letter->body_snapshot, 'names are escaped in the HTML body');
        $this->assertStringContainsString('11 November 2026', $letter->body_snapshot);
        $this->assertStringContainsString('Ada Admin', $letter->body_snapshot);
        Storage::assertExists($letter->pdf_path);
    }

    public function test_letters_can_only_go_to_your_own_organisation(): void
    {
        $admin = $this->makeUser('admin');
        $outsider = $this->makeUser('worker');
        $this->actingAs($admin);

        Livewire::test(GenerateLetter::class)
            ->fillForm([
                'worker_id' => $outsider->id,
                'letter_template_id' => LetterTemplate::first()->id,
                'subject' => 'Hi',
                'body' => '<p>Hi</p>',
            ])
            ->call('submit')
            ->assertHasErrors(['data.worker_id']);
    }

    public function test_only_one_letterhead_is_in_use(): void
    {
        $admin = $this->makeUser('admin');
        $first = CompanyLetterhead::create(['organisation_id' => $admin->organisation_id, 'company_name' => 'Old', 'is_active' => true]);
        $second = CompanyLetterhead::create(['organisation_id' => $admin->organisation_id, 'company_name' => 'New', 'is_active' => true]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_issued_letters_show_acknowledgement_and_can_be_downloaded(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $template = LetterTemplate::where('letter_type', 'warning')->firstOrFail();
        $letter = app(\App\Services\LetterService::class)->generateAndDeliver($template, $worker, $admin, 'Warning', '<p>Body</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $letter->body_snapshot);

        $this->actingAs($admin);
        $this->get(IssuedLetterResource::getUrl('index'))->assertOk()->assertSee('Waiting');

        Livewire::test(ListIssuedLetters::class)
            ->callTableAction('download', $letter)
            ->assertFileDownloaded('warning.pdf');
    }

    public function test_worker_sees_and_uses_the_acknowledge_button_info(): void
    {
        $admin = $this->makeUser('admin');
        $worker = $this->makeUser('worker', $admin->organisation);
        $template = LetterTemplate::where('letter_type', 'warning')->firstOrFail();
        $letter = app(\App\Services\LetterService::class)->generateAndDeliver($template, $worker, $admin, 'Warning', '<p>Body</p>');
        $message = Message::where('generated_letter_id', $letter->id)->firstOrFail();

        Sanctum::actingAs($worker);
        $this->getJson("/api/v1/inbox/{$message->id}")
            ->assertJsonPath('letter.requires_acknowledgement', true)
            ->assertJsonPath('letter.acknowledged_at', null);

        $this->postJson("/api/v1/inbox/{$message->id}/acknowledge")->assertOk();

        $this->getJson("/api/v1/inbox/{$message->id}")->assertJsonPath('letter.acknowledged_at', fn ($v) => $v !== null);
    }
}
