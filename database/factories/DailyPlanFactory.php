<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Organisation;
use App\Enums\Priority;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organisation_id' => Organisation::factory(),
            'date' => fake()->date(),
            'task_name' => fake()->sentence(4),
            'project_client' => fake()->company(),
            'priority' => fake()->randomElement(Priority::cases()),
            'expected_duration_minutes' => fake()->numberBetween(30, 240),
            'notes' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(PlanStatus::cases()),
        ];
    }
}
