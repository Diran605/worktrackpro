<?php

namespace Tests;

use App\Enums\SessionStatus;
use App\Models\Department;
use App\Models\Organisation;
use App\Models\User;
use App\Models\WorkSession;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedRoles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * Create a user with a role in the given (or a new) organisation.
     */
    protected function makeUser(string $role = 'worker', ?Organisation $org = null, ?Department $department = null, array $attributes = []): User
    {
        $org ??= Organisation::factory()->create();

        $user = User::factory()->create(array_merge([
            'organisation_id' => $org->id,
            'department_id' => $department?->id,
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }

    protected function clockIn(User $user): WorkSession
    {
        return WorkSession::create([
            'user_id' => $user->id,
            'organisation_id' => $user->organisation_id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'status' => SessionStatus::Active,
        ]);
    }
}
