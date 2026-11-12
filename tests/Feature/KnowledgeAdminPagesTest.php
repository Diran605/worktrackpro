<?php

namespace Tests\Feature;

use App\Filament\Resources\ManualChanges\ManualChangeRequestResource;
use App\Filament\Resources\ManualChanges\Pages\ViewManualChangeRequest;
use App\Filament\Resources\ManualSections\ManualSectionResource;
use App\Filament\Resources\ManualSections\Pages\ListManualSections;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Systems\SystemResource;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Renders the new admin pages and runs their main actions, so Filament wiring mistakes show up in CI.
 */
class KnowledgeAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;
    private System $system;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->admin = $this->makeUser('admin');
        $this->worker = $this->makeUser('worker', $this->admin->organisation);
        $this->system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'CRM', 'description' => '<p>Customer records</p>']);
        $this->system->members()->create(['user_id' => $this->admin->id, 'role' => SystemMember::ROLE_OWNER]);
    }

    private function submittedChange(string $title = 'Leads'): ManualChangeRequest
    {
        $change = ManualChangeRequest::create([
            'system_id' => $this->system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => $title,
            'body' => '<p>How leads work</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $this->worker->id,
        ]);

        return app(ManualService::class)->submit($change, $this->worker);
    }

    public function test_admin_pages_render(): void
    {
        $this->submittedChange();
        $this->actingAs($this->admin);

        $this->get(SystemResource::getUrl('index'))->assertOk()->assertSee('CRM');
        $this->get(SystemResource::getUrl('create'))->assertOk();
        $this->get(SystemResource::getUrl('edit', ['record' => $this->system]))->assertOk();
        $this->get(ManualChangeRequestResource::getUrl('index'))->assertOk()->assertSee('Leads');
        $this->get(ManualSectionResource::getUrl('index'))->assertOk();
        $this->get(RoleResource::getUrl('index'))->assertOk();
    }

    public function test_owner_approves_from_the_review_page(): void
    {
        $change = $this->submittedChange();
        $this->actingAs($this->admin);

        $this->get(ManualChangeRequestResource::getUrl('view', ['record' => $change]))
            ->assertOk()
            ->assertSee('How leads work');

        Livewire::test(ViewManualChangeRequest::class, ['record' => $change->getRouteKey()])
            ->callAction('approve')
            ->assertHasNoActionErrors()
            ->assertRedirect(ManualChangeRequestResource::getUrl('index'));

        $this->assertSame(ManualChangeRequest::STATUS_APPROVED, $change->fresh()->status);
        $this->assertSame(1, ManualSection::published()->count());
    }

    public function test_owner_sends_back_with_a_comment(): void
    {
        $change = $this->submittedChange();
        $this->actingAs($this->admin);

        Livewire::test(ViewManualChangeRequest::class, ['record' => $change->getRouteKey()])
            ->callAction('requestChanges', data: ['comment' => 'Please add screenshots'])
            ->assertHasNoActionErrors();

        $this->assertSame('Please add screenshots', $change->fresh()->review_comment);
    }

    public function test_structure_actions_reorder_sections(): void
    {
        $service = app(ManualService::class);
        $service->approve($this->submittedChange('First'), $this->admin);
        $service->approve($this->submittedChange('Second'), $this->admin);
        $second = ManualSection::where('title', 'Second')->first();

        $this->actingAs($this->admin);

        Livewire::test(ListManualSections::class)
            ->callTableAction('moveUp', $second)
            ->assertHasNoTableActionErrors();

        $this->assertSame('1', $second->fresh()->number);
    }

    public function test_drafts_are_hidden_from_reviewers(): void
    {
        ManualChangeRequest::create([
            'system_id' => $this->system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'title' => 'Private draft',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $this->worker->id,
        ]);

        $this->actingAs($this->admin);
        $this->get(ManualChangeRequestResource::getUrl('index') . '?filters[status][value]=')->assertDontSee('Private draft');
    }
}
