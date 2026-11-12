<?php

namespace Tests\Feature;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\DocsAccessCode;
use App\Models\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->makeUser('admin');
    }

    public function test_updates_record_only_the_changed_fields(): void
    {
        $this->actingAs($this->admin);
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'CRM', 'summary' => 'Old']);

        $system->update(['summary' => 'New']);

        $log = AuditLog::where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['summary' => 'Old'], $log->old_values);
        $this->assertSame(['summary' => 'New'], $log->new_values);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_touching_only_timestamps_is_not_logged(): void
    {
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'CRM']);
        $before = AuditLog::count();

        $system->touch();

        $this->assertSame($before, AuditLog::count());
    }

    public function test_hidden_values_are_masked(): void
    {
        $code = new DocsAccessCode(['organisation_id' => $this->admin->organisation_id, 'name' => 'Client']);
        $plain = $code->assignNewCode();
        $code->save();

        $log = AuditLog::where('auditable_type', DocsAccessCode::class)->firstOrFail();
        $this->assertSame('********', $log->new_values['code']);
        $this->assertStringNotContainsString($plain, json_encode($log->new_values));
    }

    public function test_page_is_read_only_newest_first_with_details(): void
    {
        $this->actingAs($this->admin);
        $system = System::create(['organisation_id' => $this->admin->organisation_id, 'name' => 'Payments']);
        $system->update(['summary' => 'Takes card payments']);

        $this->get(AuditLogResource::getUrl('index'))
            ->assertOk()
            ->assertDontSee('New audit log');

        $latest = AuditLog::where('auditable_type', System::class)->where('action', 'updated')->latest('id')->first();

        Livewire::test(ListAuditLogs::class)
            ->loadTable()
            ->assertCanSeeTableRecords(AuditLog::orderByDesc('created_at')->orderByDesc('id')->take(5)->get(), inOrder: true)
            ->assertSee('Payments')
            ->mountTableAction('details', $latest)
            ->assertHasNoErrors();

        // The details window: only the changed field, before and after.
        $html = view('filament.audit-log-details', [
            'record' => $latest,
            'rows' => \App\Filament\Resources\AuditLogs\Tables\AuditLogsTable::rows($latest),
            'name' => \App\Filament\Resources\AuditLogs\Tables\AuditLogsTable::recordName($latest),
            'timezone' => 'UTC',
        ])->render();

        $this->assertStringContainsString('Takes card payments', $html);
        $this->assertStringContainsString('Before', $html);
        $this->assertStringContainsString('Payments', $html);
        $this->assertStringNotContainsString('Organisation Id', $html);
    }

    public function test_admins_only_see_their_organisation(): void
    {
        $other = $this->makeUser('admin');
        System::create(['organisation_id' => $other->organisation_id, 'name' => 'Not mine']);

        $this->actingAs($this->admin);

        Livewire::test(ListAuditLogs::class)->loadTable()->assertSee($this->admin->name)->assertDontSee('Not mine');
    }
}
