<?php

namespace App\Console\Commands;

use App\Models\DailyPlan;
use App\Models\PersonalRecurringTask;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Console\Command;

class GenerateRecurringPlans extends Command
{
    protected $signature = 'worktrack:plans:generate-recurring {--date=}';

    protected $description = 'Generate daily plans from task templates and personal recurring tasks';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $dayOfWeek = (int) now()->parse($date)->dayOfWeek; // 0 (Sun) - 6 (Sat)

        $generated = 0;

        $templates = TaskTemplate::query()
            ->where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            if (!$this->isDue($template->recurrence_type, (int) $template->recurrence_day, $dayOfWeek)) {
                continue;
            }

            $users = $this->resolveTemplateUsers($template);

            foreach ($users as $user) {
                $exists = DailyPlan::query()
                    ->where('user_id', $user->id)
                    ->whereDate('date', $date)
                    ->where('task_template_id', $template->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DailyPlan::create([
                    'user_id' => $user->id,
                    'organisation_id' => $user->organisation_id,
                    'assigned_by' => $template->created_by,
                    'date' => $date,
                    'task_name' => $template->title,
                    'priority' => 'medium',
                    'expected_duration_minutes' => (int) $template->expected_duration_minutes,
                    'status' => 'pending',
                    'is_assigned' => true,
                    'task_template_id' => $template->id,
                ]);

                $generated++;
            }
        }

        $personal = PersonalRecurringTask::query()
            ->where('is_active', true)
            ->with('user:id,organisation_id,is_active')
            ->get();

        foreach ($personal as $task) {
            if (!$task->user || !$task->user->is_active) {
                continue;
            }
            if (!$this->isDue($task->recurrence_type, (int) $task->recurrence_day, $dayOfWeek)) {
                continue;
            }

            $exists = DailyPlan::query()
                ->where('user_id', $task->user_id)
                ->whereDate('date', $date)
                ->where('personal_recurring_task_id', $task->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DailyPlan::create([
                'user_id' => $task->user_id,
                'organisation_id' => $task->user->organisation_id,
                'assigned_by' => null,
                'date' => $date,
                'task_name' => $task->title,
                'priority' => $task->priority,
                'expected_duration_minutes' => (int) $task->expected_duration_minutes,
                'status' => 'pending',
                'is_assigned' => false,
                'personal_recurring_task_id' => $task->id,
            ]);

            $generated++;
        }

        $this->info("Generated {$generated} plan(s) for {$date}.");

        return Command::SUCCESS;
    }

    private function isDue(string $recurrenceType, ?int $recurrenceDay, int $dayOfWeek): bool
    {
        return match ($recurrenceType) {
            'daily' => true,
            'weekly' => $recurrenceDay !== null && $recurrenceDay === $dayOfWeek,
            'one_time' => true,
            default => false,
        };
    }

    private function resolveTemplateUsers(TaskTemplate $template)
    {
        $q = User::query()
            ->where('organisation_id', $template->organisation_id)
            ->where('is_active', true);

        if ($template->assign_to_all) {
            return $q->get();
        }

        if ($template->department_id) {
            return $q->where('department_id', $template->department_id)->get();
        }

        $assignedIds = $template->assignedUsers()->pluck('users.id');

        if ($assignedIds->isEmpty()) {
            return collect();
        }

        return $q->whereIn('id', $assignedIds)->get();
    }
}

