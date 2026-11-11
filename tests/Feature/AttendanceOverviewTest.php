<?php

namespace Tests\Feature;

use App\Enums\SessionStatus;
use App\Filament\Pages\AttendanceOverview;
use App\Models\Department;
use App\Models\Organisation;
use App\Models\WorkSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->travelTo(Carbon::parse('2026-11-11 15:00:00', 'UTC')); // 16:00 in Lagos
    }

    public function test_choosing_a_date_shows_that_days_sessions_in_local_time(): void
    {
        $org = Organisation::factory()->create(['timezone' => 'Africa/Lagos']);
        $dept = Department::factory()->create(['organisation_id' => $org->id]);
        $admin = $this->makeUser('admin', $org, $dept, ['name' => 'Admin']);
        $alice = $this->makeUser('worker', $org, $dept, ['name' => 'Alice']);
        $bob = $this->makeUser('worker', $org, $dept, ['name' => 'Bob']);

        // Alice worked yesterday 08:00–16:30 UTC = 09:00–17:30 in Lagos
        WorkSession::create([
            'user_id' => $alice->id,
            'organisation_id' => $org->id,
            'date' => '2026-11-10',
            'clock_in' => Carbon::parse('2026-11-10 08:00:00', 'UTC'),
            'clock_out' => Carbon::parse('2026-11-10 16:30:00', 'UTC'),
            'total_minutes' => 510,
            'status' => SessionStatus::Closed,
        ]);
        // Bob is clocked in today
        WorkSession::create([
            'user_id' => $bob->id,
            'organisation_id' => $org->id,
            'date' => '2026-11-11',
            'clock_in' => Carbon::parse('2026-11-11 07:30:00', 'UTC'),
            'status' => SessionStatus::Active,
        ]);

        $this->actingAs($admin);
        $this->get(AttendanceOverview::getUrl())->assertOk()->assertSee('Wednesday, 11 November 2026');

        Livewire::test(AttendanceOverview::class)
            ->assertCanSeeTableRecords([$admin, $alice, $bob])
            ->assertSee('Clocked in')
            ->assertSee('7h 30m so far')
            ->filterTable('date', ['date' => '2026-11-10'])
            ->assertSee('Tuesday, 10 November 2026')
            ->assertSee('09:00')
            ->assertSee('17:30')
            ->assertSee('8h 30m')
            ->assertSee('Present')
            ->filterTable('status', 'absent')
            ->assertCanSeeTableRecords([$admin, $bob])
            ->assertCanNotSeeTableRecords([$alice]);
    }

    public function test_admins_only_see_their_department(): void
    {
        $org = Organisation::factory()->create();
        $mine = Department::factory()->create(['organisation_id' => $org->id]);
        $other = Department::factory()->create(['organisation_id' => $org->id]);
        $admin = $this->makeUser('admin', $org, $mine);
        $colleague = $this->makeUser('worker', $org, $mine);
        $stranger = $this->makeUser('worker', $org, $other);

        $this->actingAs($admin);

        Livewire::test(AttendanceOverview::class)
            ->assertCanSeeTableRecords([$admin, $colleague])
            ->assertCanNotSeeTableRecords([$stranger]);
    }
}
