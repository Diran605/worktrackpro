<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreign('task_template_id')->references('id')->on('task_templates')->nullOnDelete();
            $table->foreign('personal_recurring_task_id')->references('id')->on('personal_recurring_tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropForeign(['task_template_id']);
            $table->dropForeign(['personal_recurring_task_id']);
        });
    }
};

