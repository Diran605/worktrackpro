<?php

namespace Tests\Feature;

use App\Enums\TimerStatus;
use App\Filament\Pages\PlatformSettings;
use App\Filament\Resources\DocsAccessCodes\DocsAccessCodeResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\Pages\EditOrganisation;
use App\Filament\Resources\Systems\SystemResource;
use App\Models\DailyPlan;
use App\Models\Organisation;
use App\Models\OrganisationSetting;
use App\Models\System;
use App\Models\User;
use App\Services\PlatformService;
use App\Support\Modules;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3C: modules per organisation, company mode, and attendance linked (or not) to work tracking.
 */
class PlatformModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withoutVite();
        $this->admin = $this->makeUser('admin');
    }

    private function onlyModules(array $modules, ?Organisation $org = null): void
    {
        Modules::setFor($org ?? $this->admin->organisation, $modules);
    }

    private function planFor(User $user): DailyPlan
    {
        return DailyPlan::factory()->create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => now()->toDateString(),
            'status' => 'pending',
            'work_type' => 'indirect',
        ]);
    }

    // ---------------------------------------------------------------------------------------------
    // Modules
    // ---------------------------------------------------------------------------------------------

    public function test_new_organisations_get_every_module(): void
    {
        $this->assertSame(Modules::keys(), Modules::enabledFor($this->admin->organisation));
    }

    public function test_the_library_needs_the_knowledge_base(): void
    {
        $this->assertSame([Modules::KNOWLEDGE, Modules::DOCS_LIBRARY], Modules::withRequirements([Modules::DOCS_LIBRARY]));
    }

    public function test_switched_off_modules_are_invisible_in_the_admin_panel(): void
    {
        $this->onlyModules([Modules::WORK_TRACKING]);
        $this->actingAs($this->admin);

        $this->get(SystemResource::getUrl('index'))->assertForbidden();
        $this->get(DocsAccessCodeResource::getUrl('index'))->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Manual Approvals')->assertDontSee('Access Codes');

        $this->onlyModules([Modules::KNOWLEDGE]);
        $this->get(SystemResource::getUrl('index'))->assertOk();
        $this->get(\App\Filament\Resources\DailyPlans\DailyPlanResource::getUrl('index'))->assertForbidden();
        $this->get(\App\Filament\Pages\AttendanceOverview::getUrl())->assertForbidden();
    }

    public function test_switched_off_modules_are_refused_by_the_api_and_hidden_in_the_portal(): void
    {
        $this->onlyModules([Modules::KNOWLEDGE]);
        $worker = $this->makeUser('worker', $this->admin->organisation);
        Sanctum::actingAs($worker);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.modules', [Modules::KNOWLEDGE])
            ->assertJsonPath('data.attendance_linked', false);
        $this->getJson('/api/v1/plans')->assertForbidden();
        $this->getJson('/api/v1/sessions/current')->assertForbidden();
        $this->getJson('/api/v1/systems')->assertOk();
    }

    public function test_super_admins_see_every_module(): void
    {
        $super = $this->makeUser('super_admin', $this->admin->organisation);
        $this->onlyModules([]);
        $this->actingAs($super);

        $this->get(SystemResource::getUrl('index'))->assertOk();
        Sanctum::actingAs($super);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.modules', Modules::keys());
    }

    public function test_library_without_the_module(): void
    {
        System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'POS', 'library_visibility' => System::LIBRARY_PUBLIC]);
        $this->onlyModules([Modules::KNOWLEDGE]);

        $this->get('/')->assertRedirect('/staff');
        $this->get('/docs/pos')->assertNotFound();
    }

    public function test_switching_a_module_back_on_restores_its_data(): void
    {
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'CRM']);
        $this->onlyModules([]);
        $this->onlyModules(Modules::keys());

        $this->actingAs($this->admin)->get(SystemResource::getUrl('index'))->assertOk()->assertSee('CRM');
        $this->assertNotNull($system->fresh());
    }

    public function test_jobs_skip_organisations_without_the_module(): void
    {
        $worker = $this->makeUser('worker', $this->admin->organisation);
        $plan = $this->planFor($worker);
        $plan->update(['timer_status' => TimerStatus::Running, 'timer_started_at' => now()->subHours(10)]);
        $this->onlyModules([Modules::KNOWLEDGE]);

        $this->artisan('worktrack:timers:stop-abandoned')->assertSuccessful();

        $this->assertSame(TimerStatus::Running, $plan->fresh()->timer_status);
    }

    // ---------------------------------------------------------------------------------------------
    // Attendance linked to work tracking (or not)
    // ---------------------------------------------------------------------------------------------

    public function test_linked_by_default_timers_need_a_clock_in(): void
    {
        $worker = $this->makeUser('worker', $this->admin->organisation);
        Sanctum::actingAs($worker);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.attendance_linked', true);
        $this->postJson('/api/v1/timers/' . $this->planFor($worker)->id . '/start')->assertStatus(422);
    }

    public function test_unlinked_attendance_is_only_a_record_of_hours(): void
    {
        OrganisationSetting::updateOrCreate(['organisation_id' => $this->admin->organisation_id], ['link_attendance_to_work' => false]);
        app(\App\Services\OrganisationSettingsService::class)->clearCache($this->admin->organisation_id);
        $worker = $this->makeUser('worker', $this->admin->organisation);
        Sanctum::actingAs($worker);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.attendance_linked', false);

        // Timers run without clocking in…
        $plan = $this->planFor($worker);
        $this->postJson("/api/v1/timers/{$plan->id}/start")->assertOk();

        // …and clocking out neither asks about unfinished tasks nor stops the timer.
        $this->postJson('/api/v1/sessions/clock-in')->assertCreated();
        $this->postJson('/api/v1/sessions/clock-out')->assertOk();
        $this->assertSame(TimerStatus::Running, $plan->fresh()->timer_status);
    }

    public function test_work_tracking_without_attendance_needs_no_clock_in(): void
    {
        $this->onlyModules([Modules::WORK_TRACKING]);
        $worker = $this->makeUser('worker', $this->admin->organisation);
        Sanctum::actingAs($worker);

        $this->postJson('/api/v1/timers/' . $this->planFor($worker)->id . '/start')->assertOk();
    }

    // ---------------------------------------------------------------------------------------------
    // Company mode
    // ---------------------------------------------------------------------------------------------

    public function test_single_mode_hides_organisations(): void
    {
        $super = $this->makeUser('super_admin', $this->admin->organisation);
        $other = Organisation::factory()->create(['slug' => 'globex']);
        app(PlatformService::class)->apply(Platform::SINGLE, $this->admin->organisation);

        $this->assertFalse($other->fresh()->is_active);
        $this->assertTrue(Platform::isSingle());

        $this->actingAs($super);
        $this->assertFalse(OrganisationResource::shouldRegisterNavigation());
        $this->get(OrganisationResource::getUrl('create'))->assertForbidden();
        $this->get(SystemResource::getUrl('index'))->assertOk()->assertDontSee('Organisation</span>', false);
        $this->get('/org/globex')->assertNotFound();

        // Back to multi: the menu returns; reactivating the organisation is up to the super admin.
        app(PlatformService::class)->apply(Platform::MULTI, $this->admin->organisation);
        $this->assertTrue(OrganisationResource::shouldRegisterNavigation());
        $this->get(OrganisationResource::getUrl('create'))->assertOk();
        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_platform_settings_page(): void
    {
        $super = $this->makeUser('super_admin', $this->admin->organisation);
        Organisation::factory()->create(['name' => 'Globex']);

        $this->actingAs($this->admin)->get(PlatformSettings::getUrl())->assertForbidden();
        $this->actingAs($super)->get(PlatformSettings::getUrl())->assertOk()->assertSee('Company mode');

        Livewire::test(PlatformSettings::class)
            ->set('data.company_mode', Platform::SINGLE)
            ->set('data.main_organisation_id', $this->admin->organisation_id)
            ->set('data.modules', [Modules::DOCS_LIBRARY])
            ->call('save');

        $this->assertTrue(Platform::isSingle());
        $this->assertSame($this->admin->organisation_id, Platform::mainOrganisation()->id);
        $this->assertSame([Modules::KNOWLEDGE, Modules::DOCS_LIBRARY], Modules::enabledFor($this->admin->organisation));
        $this->assertFalse(Organisation::where('name', 'Globex')->first()->is_active);
    }

    public function test_modules_on_the_organisation_form(): void
    {
        Platform::set('company_mode', Platform::MULTI);
        $super = $this->makeUser('super_admin', $this->admin->organisation);
        $this->actingAs($super);

        Livewire::test(EditOrganisation::class, ['record' => $this->admin->organisation->getRouteKey()])
            ->assertFormSet(['modules' => Modules::keys()])
            ->set('data.modules', [Modules::ATTENDANCE, Modules::DOCS_LIBRARY]) // the browser sends the whole list
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([Modules::ATTENDANCE, Modules::KNOWLEDGE, Modules::DOCS_LIBRARY], Modules::enabledFor($this->admin->organisation));
    }

    public function test_mode_command(): void
    {
        Organisation::factory()->create(['slug' => 'globex']);

        $this->artisan('worktrack:platform:mode', ['mode' => 'single', '--main' => $this->admin->organisation->slug])
            ->expectsConfirmation('These organisations will be made inactive (not deleted): ' . Organisation::where('slug', 'globex')->value('name') . '. Continue?', 'yes')
            ->assertSuccessful();

        $this->assertTrue(Platform::isSingle());
        $this->artisan('worktrack:platform:mode', ['mode' => 'nonsense'])->assertFailed();
    }
}
