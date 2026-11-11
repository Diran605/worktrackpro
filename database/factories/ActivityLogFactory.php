<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Organisation;
use App\Models\DailyPlan;
use App\Enums\WorkType;
use App\Enums\CompletionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 week', 'now');
        $end = (clone $start)->modify('+' . fake()->numberBetween(15, 120) . ' minutes');

        return [
            'user_id' => User::factory(),
            'organisation_id' => Organisation::factory(),
            'daily_plan_id' => null, // Optionally set in states
            'date' => $start->format('Y-m-d'),
            'task_name' => fake()->sentence(4),
            'project_client' => fake()->company(),
            'work_type' => fake()->randomElement(WorkType::cases()),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'duration_minutes' => ($end->getTimestamp() - $start->getTimestamp()) / 60,
            'output' => fake()->paragraph(),
            'completion_type' => fake()->randomElement(CompletionType::cases()),
            'is_planned' => fake()->boolean(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
