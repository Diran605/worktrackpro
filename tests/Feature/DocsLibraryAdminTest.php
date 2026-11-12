<?php

namespace Tests\Feature;

use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use App\Filament\Resources\DocsAccessCodes\Pages\CreateDocsAccessCode;
use App\Filament\Resources\DocsAccessCodes\Pages\EditDocsAccessCode;
use App\Filament\Resources\DocsLibrary\DocsAccessRequestResource;
use App\Filament\Resources\DocsLibrary\DocsFeedbackResource;
use App\Filament\Resources\DocsLibrary\ManualReleaseResource;
use App\Filament\Resources\DocsLibrary\SystemCategoryResource;
use App\Filament\Resources\ManualSections\Pages\ListManualSections;
use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Resources\Systems\Pages\ListSystems;
use App\Models\DocsAccessCode;
use App\Models\DocsAccessRequest;
use App\Models\ManualChangeRequest;
use App\Models\ManualRelease;
use App\Models\ManualSection;
use App\Models\OrganisationSetting;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin panel pages for the documentation library (Phase 3B).
 */
class DocsLibraryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private System $system;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->admin = $this->makeUser('admin');
        $this->system = System::create([
            'organisation_id' => $this->admin->organisation_id,
            'name' => 'CRM',
            'library_visibility' => System::LIBRARY_RESTRICTED,
        ]);
        $this->system->members()->create(['user_id' => $this->admin->id, 'role' => SystemMember::ROLE_OWNER]);
    }

    private function publishedSection(string $title = 'Leads'): ManualSection
    {
        $change = ManualChangeRequest::create([
            'system_id' => $this->system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => $title,
            'body' => '<p>How leads work</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $this->admin->id,
        ]);
        app(ManualService::class)->submit($change, $this->admin); // owner: approved straight away

        return ManualSection::findOrFail($change->fresh()->manual_section_id);
    }

    public function test_library_admin_pages_render(): void
    {
        $this->publishedSection();
        DocsAccessRequest::create(['system_id' => $this->system->id, 'name' => 'Jane Client', 'email' => 'jane@example.test']);
        $this->actingAs($this->admin);

        $this->get(SystemCategoryResource::getUrl('index'))->assertOk();
        $this->get(ManualReleaseResource::getUrl('index'))->assertOk();
        $this->get(DocsAccessCodeResource::getUrl('index'))->assertOk();
        $this->get(DocsAccessCodeResource::getUrl('create'))->assertOk();
        $this->get(DocsAccessRequestResource::getUrl('index'))->assertOk()->assertSee('Jane Client');
        $this->get(DocsFeedbackResource::getUrl('index'))->assertOk();

        $setting = OrganisationSetting::firstOrCreate(['organisation_id' => $this->admin->organisation_id]);
        $this->get(OrganisationSettingResource::getUrl('edit', ['record' => $setting]))->assertOk()->assertSee('Documentation library');
    }

    public function test_workers_cannot_open_library_admin_pages(): void
    {
        $this->actingAs($this->makeUser('worker', $this->admin->organisation));

        $this->get(DocsAccessCodeResource::getUrl('index'))->assertForbidden();
        $this->get(SystemCategoryResource::getUrl('index'))->assertForbidden();
    }

    public function test_owner_publishes_a_version_from_the_systems_table(): void
    {
        $this->publishedSection();
        $this->actingAs($this->admin);

        Livewire::test(ListSystems::class)
            ->assertTableActionVisible('publishVersion', $this->system)
            ->callTableAction('publishVersion', $this->system, data: ['is_major' => '1', 'summary' => 'First version'])
            ->assertHasNoTableActionErrors();

        $release = ManualRelease::firstOrFail();
        $this->assertSame('1.0', $release->version);
        $this->assertSame('First version', $release->summary);

        // Nothing left to publish: the action is hidden.
        Livewire::test(ListSystems::class)->assertTableActionHidden('publishVersion', $this->system);
    }

    public function test_approving_with_publish_now_releases_a_minor_version(): void
    {
        $this->publishedSection('Leads');
        app(\App\Services\ManualReleaseService::class)->publish($this->system->fresh(), $this->admin, false, 'First');

        $worker = $this->makeUser('worker', $this->admin->organisation);
        $change = ManualChangeRequest::create([
            'system_id' => $this->system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => 'Phone numbers',
            'body' => '<p>Call 123</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $worker->id,
        ]);
        app(ManualService::class)->submit($change, $worker);
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Resources\ManualChanges\Pages\ViewManualChangeRequest::class, ['record' => $change->getRouteKey()])
            ->callAction('approve', data: ['publish_now' => true, 'release_summary' => 'Corrected the phone number'])
            ->assertHasNoActionErrors();

        $release = ManualRelease::latest('id')->firstOrFail();
        $this->assertSame('0.2', $release->version);
        $this->assertSame('Corrected the phone number', $release->summary);
        $this->assertSame(0, $this->system->fresh()->unreleasedRevisions()->count());
    }

    public function test_publish_now_needs_a_summary(): void
    {
        $worker = $this->makeUser('worker', $this->admin->organisation);
        $change = ManualChangeRequest::create([
            'system_id' => $this->system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => 'Leads',
            'body' => '<p>x</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $worker->id,
        ]);
        app(ManualService::class)->submit($change, $worker);
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Resources\ManualChanges\Pages\ViewManualChangeRequest::class, ['record' => $change->getRouteKey()])
            ->callAction('approve', data: ['publish_now' => true, 'release_summary' => ''])
            ->assertHasActionErrors(['release_summary' => 'required']);

        $this->assertSame(0, ManualRelease::count());
    }

    public function test_audiences_are_managed_and_assigned_to_chapters_and_codes(): void
    {
        $section = $this->publishedSection();
        $this->actingAs($this->admin);

        $this->get(\App\Filament\Resources\DocsLibrary\DocsAudienceResource::getUrl('index'))->assertOk();

        Livewire::test(\App\Filament\Resources\DocsLibrary\Pages\ListDocsAudiences::class)
            ->callAction('create', data: ['name' => 'Teachers', 'position' => 0])
            ->assertHasNoActionErrors();
        $teachers = \App\Models\DocsAudience::where('name', 'Teachers')->firstOrFail();
        $this->assertSame($this->admin->organisation_id, $teachers->organisation_id);

        // Another organisation's audience can't be attached.
        $foreign = \App\Models\DocsAudience::create(['organisation_id' => $this->makeUser('admin')->organisation_id, 'name' => 'Theirs']);

        Livewire::test(ListManualSections::class)
            ->callTableAction('audiences', $section, data: ['audience_ids' => [$teachers->id, $foreign->id]]);
        $this->assertNotContains($foreign->id, $section->audiences()->pluck('docs_audiences.id')->all());

        Livewire::test(ListManualSections::class)
            ->callTableAction('audiences', $section, data: ['audience_ids' => [$teachers->id]])
            ->assertHasNoTableActionErrors();
        $this->assertSame([$teachers->id], $section->audiences()->pluck('docs_audiences.id')->all());

        Livewire::test(CreateDocsAccessCode::class)
            ->fillForm([
                'name' => 'Teacher',
                'is_active' => true,
                'grants' => [['system_id' => $this->system->id, 'include_restricted' => false, 'audiences' => [$teachers->id]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $grant = DocsAccessCode::where('name', 'Teacher')->firstOrFail()->grants()->firstOrFail();
        $this->assertSame([$teachers->id], $grant->audiences()->pluck('docs_audiences.id')->all());
    }

    public function test_sections_can_be_restricted_from_manual_structure(): void
    {
        $section = $this->publishedSection();
        $this->actingAs($this->admin);

        Livewire::test(ListManualSections::class)->callTableAction('restrict', $section);
        $this->assertSame(ManualSection::VISIBILITY_RESTRICTED, $section->fresh()->visibility);

        Livewire::test(ListManualSections::class)->callTableAction('unrestrict', $section);
        $this->assertSame(ManualSection::VISIBILITY_INHERIT, $section->fresh()->visibility);
    }

    public function test_creating_an_access_code_and_regenerating_it(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateDocsAccessCode::class)
            ->fillForm([
                'name' => 'Globex finance team',
                'is_active' => true,
                'grants' => [['system_id' => $this->system->id, 'include_restricted' => true]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $code = DocsAccessCode::with('grants')->firstOrFail();
        $this->assertSame($this->admin->organisation_id, $code->organisation_id);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code->code);
        $this->assertTrue($code->grants->first()->include_restricted);

        $old = $code->code_hash;
        Livewire::test(EditDocsAccessCode::class, ['record' => $code->getRouteKey()])
            ->assertFormSet(['code' => $code->code])
            ->callAction('regenerate');
        $this->assertNotSame($old, $code->fresh()->code_hash);
    }

    public function test_access_codes_of_other_organisations_are_not_reachable(): void
    {
        $other = $this->makeUser('admin');
        $code = new DocsAccessCode(['organisation_id' => $other->organisation_id, 'name' => 'Theirs']);
        $code->assignNewCode();
        $code->save();

        $this->actingAs($this->admin)
            ->get(DocsAccessCodeResource::getUrl('edit', ['record' => $code]))
            ->assertNotFound();
    }

    public function test_super_admin_sets_a_docs_domain(): void
    {
        $super = $this->makeUser('super_admin', $this->admin->organisation);
        $organisation = $this->admin->organisation;
        $this->actingAs($super);

        Livewire::test(EditOrganisation::class, ['record' => $organisation->getRouteKey()])
            ->fillForm(['docs_domain' => 'https://bad/'])
            ->call('save')
            ->assertHasFormErrors(['docs_domain']);

        Livewire::test(EditOrganisation::class, ['record' => $organisation->getRouteKey()])
            ->fillForm(['docs_domain' => 'Docs.Example.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('docs.example.com', $organisation->fresh()->docs_domain);

        // Admins can't reach the Organisations resource at all.
        $this->actingAs($this->admin)->get(OrganisationResource::getUrl('index'))->assertForbidden();
    }
}
