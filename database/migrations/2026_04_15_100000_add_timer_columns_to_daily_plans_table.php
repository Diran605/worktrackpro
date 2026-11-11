<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->string('timer_status')->default('idle')->after('status');
            $table->timestamp('timer_started_at')->nullable()->after('timer_status');
            $table->integer('timer_accumulated_minutes')->default(0)->after('timer_started_at');

            $table->foreignId('work_session_id')->nullable()->after('timer_accumulated_minutes')
                ->constrained('work_sessions')->nullOnDelete();

            $table->boolean('is_assigned')->default(false)->after('work_session_id');
            $table->foreignId('task_template_id')->nullable()->after('is_assigned');
            $table->foreignId('personal_recurring_task_id')->nullable()->after('task_template_id');

            $table->foreignId('carried_from_plan_id')->nullable()->after('personal_recurring_task_id')
                ->constrained('daily_plans')->nullOnDelete();
            $table->integer('carry_over_count')->default(0)->after('carried_from_plan_id');

            $table->index(['user_id', 'timer_status']);
            $table->index(['work_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'timer_status']);
            $table->dropIndex(['work_session_id']);

            $table->dropForeign(['work_session_id']);
            $table->dropForeign(['carried_from_plan_id']);

            $table->dropColumn([
                'timer_status',
                'timer_started_at',
                'timer_accumulated_minutes',
                'work_session_id',
                'is_assigned',
                'task_template_id',
                'personal_recurring_task_id',
                'carried_from_plan_id',
                'carry_over_count',
            ]);
        });
    }
};

