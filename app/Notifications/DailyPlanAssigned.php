<?php

namespace App\Notifications;

use App\Models\DailyPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyPlanAssigned extends Notification
{
    use Queueable;

    public $plan;

    public function __construct(DailyPlan $plan)
    {
        $this->plan = $plan;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New Task Assigned',
            'message' => 'You have been assigned a new task: ' . $this->plan->task_name,
            'plan_id' => $this->plan->id,
            'assigned_by' => $this->plan->assignedByUser?->name,
            'priority' => $this->plan->priority?->value ?? $this->plan->priority,
        ];
    }
}
