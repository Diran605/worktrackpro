<?php

namespace Tests\Feature;

use App\Filament\Widgets\AttendanceTodayWidget;
use App\Filament\Widgets\AttendanceWeekChart;
use App\Filament\Widgets\CoreOverviewWidget;
use App\Filament\Widgets\KnowledgeOverviewWidget;
use App\Filament\Widgets\LettersOverviewWidget;
use App\Filament\Widgets\LibraryOverviewWidget;
use App\Filament\Widgets\PendingManualApprovalsWidget;
use App\Filament\Widgets\QuickStatsWidget;
use App\Models\Department;
use App\Models\DocsAccessRequest;
use App\Models\ManualChangeRequest;
use App\Models\ManualSection;
use App\Models\System;
use App\Models\SystemMember;
use App\Models\User;
use App\Services\ManualService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Both dashboards show something useful for any combination of modules.
 */
class ModuleDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $department = Department::factory()->create();
        $this->admin = $this->makeUser('admin', null, $department);
        $this->worker = $this->makeUser('worker', $this->admin->organisation, $department);
    }

    private function only(array $modules): void
    {
        Modules::setFor($this->admin->organisation, $modules);
    }

    /** A system owned by the admin, with one section by the worker waiting for approval. */
    private function manualWithPendingChange(): System
    {
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'POS', 'library_visibility' => System::LIBRARY_PUBLIC]);
        $system->members()->create(['user_id' => $this->admin->id, 'role' => SystemMember::ROLE_OWNER]);

        $change = ManualChangeRequest::create([
            'system_id' => $system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => 'Refunds',
            'body' => '<p>How refunds work</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $this->worker->id,
        ]);
        app(ManualService::class)->submit($change, $this->worker);

        return $system;
    }

    public function test_admin_widgets_follow_the_modules(): void
    {
        $this->actingAs($this->admin);

        $this->only([Modules::KNOWLEDGE, Modules::DOCS_LIBRARY]);
        $this->assertTrue(CoreOverviewWidget::canView());
        $this->assertTrue(KnowledgeOverviewWidget::canView());
        $this->assertTrue(LibraryOverviewWidget::canView());
        $this->assertFalse(AttendanceTodayWidget::canView());
        $this->assertFalse(QuickStatsWidget::canView());
        $this->assertFalse(LettersOverviewWidget::canView());

        $this->only([Modules::ATTENDANCE]);
        $this->assertTrue(AttendanceTodayWidget::canView());
        $this->assertTrue(AttendanceWeekChart::canView());
        $this->assertFalse(KnowledgeOverviewWidget::canView());
        $this->assertFalse(LibraryOverviewWidget::canView());

        $this->get('/admin')->assertOk();
    }

    public function test_admin_widgets_show_their_numbers(): void
    {
        $this->actingAs($this->admin);
        $system = $this->manualWithPendingChange();
        DocsAccessRequest::create(['system_id' => $system->id, 'name' => 'Ann', 'email' => 'ann@example.test']);
        $this->clockIn($this->worker);

        Livewire::test(CoreOverviewWidget::class)->assertSee('Active staff')->assertSee('Unread messages');
        Livewire::test(AttendanceTodayWidget::class)->assertSee('Clocked in now')->assertSee('of 2 active staff');
        Livewire::test(AttendanceWeekChart::class)->assertOk();
        Livewire::test(KnowledgeOverviewWidget::class)->assertSee('Waiting for your approval')->assertSee('Products');
        Livewire::test(PendingManualApprovalsWidget::class)->assertSee('Refunds')->assertSee('POS');
        Livewire::test(LibraryOverviewWidget::class)->assertSee('Access requests')->assertSee('1 public');
        Livewire::test(LettersOverviewWidget::class)->assertSee('Issued this month');
    }

    public function test_staff_overview_for_knowledge_and_library_only(): void
    {
        $this->only([Modules::KNOWLEDGE, Modules::DOCS_LIBRARY]);
        $this->manualWithPendingChange();
        Sanctum::actingAs($this->worker);

        $this->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.inbox.unread', 0)
            ->assertJsonMissingPath('data.attendance')
            ->assertJsonPath('data.knowledge.my_contributions.waiting', 1)
            ->assertJsonPath('data.knowledge.to_review', 0);

        // The owner sees the change waiting for them.
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/dashboard/overview')->assertJsonPath('data.knowledge.to_review', 1);
    }

    public function test_staff_overview_for_attendance_only(): void
    {
        $this->only([Modules::ATTENDANCE]);
        $this->clockIn($this->worker);
        Sanctum::actingAs($this->worker);

        $this->getJson('/api/v1/dashboard/overview')
            ->assertOk()
            ->assertJsonPath('data.attendance.days_present', 1)
            ->assertJsonMissingPath('data.knowledge')
            ->assertJsonMissingPath('data.library');
        $this->assertNotNull($this->getJson('/api/v1/dashboard/overview')->json('data.attendance.today_clock_in'));
    }

    public function test_owner_sees_library_changes_waiting_to_be_published(): void
    {
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'CRM', 'library_visibility' => System::LIBRARY_PUBLIC]);
        $system->members()->create(['user_id' => $this->admin->id, 'role' => SystemMember::ROLE_OWNER]);
        $change = ManualChangeRequest::create([
            'system_id' => $system->id,
            'type' => ManualChangeRequest::TYPE_CREATE,
            'kind' => ManualSection::KIND_WRITTEN,
            'title' => 'Leads',
            'body' => '<p>x</p>',
            'status' => ManualChangeRequest::STATUS_DRAFT,
            'author_id' => $this->admin->id,
        ]);
        app(ManualService::class)->submit($change, $this->admin); // owner: approved, not yet in a version

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/dashboard/overview')
            ->assertJsonPath('data.library.0.name', 'CRM')
            ->assertJsonPath('data.library.0.waiting', 1);
    }
}
