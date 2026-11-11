<?php

namespace Tests\Feature;

use App\Filament\Resources\OrganisationSettings\OrganisationSettingResource;
use App\Filament\Resources\OrganisationSettings\Pages\EditOrganisationSetting;
use App\Models\DailyPlan;
use App\Models\Organisation;
use App\Models\OrganisationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class OrganisationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admins_go_straight_to_their_own_settings_created_with_defaults(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);

        $settings = fn () => OrganisationSetting::where('organisation_id', $admin->organisation_id)->first();
        $this->assertNull($settings());

        $this->get(OrganisationSettingResource::getUrl('index'))
            ->assertRedirect(OrganisationSettingResource::getUrl('edit', ['record' => $settings()]));

        $this->assertSame(3, $settings()->abandoned_timer_hours);

        $this->get(OrganisationSettingResource::getUrl('edit', ['record' => $settings()]))
            ->assertOk()
            ->assertSee('Organisation time zone')
            ->assertSee('Close forgotten sessions at');
    }

    public function test_saving_updates_the_settings_and_the_organisation_time_zone(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin);
        $this->get(OrganisationSettingResource::getUrl('index'));
        $record = OrganisationSetting::where('organisation_id', $admin->organisation_id)->firstOrFail();

        Livewire::test(EditOrganisationSetting::class, ['record' => $record->getRouteKey()])
            ->fillForm([
                'organisation' => ['timezone' => 'Africa/Lagos'],
                'abandoned_session_close_time' => '19:30',
                'abandoned_timer_hours' => 4,
                'carry_over_flag_threshold' => 2,
                'inbox_max_attachment_kb' => 10240,
                'inbox_allowed_mime_types' => ['application/pdf', 'image/png'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('Africa/Lagos', $admin->organisation->fresh()->timezone);
        $this->assertSame('19:30', $record->abandoned_session_close_time);
        $this->assertSame(4, $record->abandoned_timer_hours);
        $this->assertSame(['application/pdf', 'image/png'], $record->inbox_allowed_mime_types);
    }

    public function test_admins_cannot_open_another_organisations_settings(): void
    {
        $admin = $this->makeUser('admin');
        $other = OrganisationSetting::create(['organisation_id' => Organisation::factory()->create()->id]);

        $this->actingAs($admin)
            ->get(OrganisationSettingResource::getUrl('edit', ['record' => $other]))
            ->assertNotFound();
    }

    public function test_super_admin_sees_a_row_for_every_organisation_and_cannot_create_more(): void
    {
        $super = $this->makeUser('super_admin');
        Organisation::factory()->count(2)->create();
        $this->actingAs($super);

        $this->get(OrganisationSettingResource::getUrl('index'))->assertOk();

        $this->assertSame(Organisation::count(), OrganisationSetting::count());
        $this->assertFalse(OrganisationSettingResource::canCreate());
    }

    public function test_plans_carried_over_past_the_threshold_are_flagged(): void
    {
        $worker = $this->makeUser('worker');
        OrganisationSetting::create(['organisation_id' => $worker->organisation_id, 'carry_over_flag_threshold' => 2]);

        foreach ([1, 2] as $count) {
            DailyPlan::factory()->create([
                'user_id' => $worker->id,
                'organisation_id' => $worker->organisation_id,
                'date' => $worker->localToday(),
                'carry_over_count' => $count,
            ]);
        }

        Sanctum::actingAs($worker);
        $flags = collect($this->getJson('/api/v1/plans')->json('data'))->pluck('carry_over_flagged', 'carry_over_count')->all();

        $this->assertSame([1 => false, 2 => true], collect($flags)->sortKeys()->all());
    }
}
