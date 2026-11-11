<?php

namespace App\Notifications;

use App\Models\DailyPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
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
            'title' => 'Task Completed',
            'message' => "{$this->plan->user->name} has completed the task: {$this->plan->task_name}",
            'plan_id' => $this->plan->id,
            'completed_by' => $this->plan->user->name,
        ];
    }
}
