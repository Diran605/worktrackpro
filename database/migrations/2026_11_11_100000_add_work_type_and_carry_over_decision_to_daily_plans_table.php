<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            // Plans had no work type, so timers had nothing valid to copy into activity_logs.work_type.
            $table->string('work_type')->default('direct')->after('priority');

            // "Leave for now" at clock-out: the plan stays pending but stops blocking clock-out that day.
            $table->string('carry_over_decision')->nullable()->after('carry_over_count');
            $table->timestamp('carry_over_decided_at')->nullable()->after('carry_over_decision');
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropColumn(['work_type', 'carry_over_decision', 'carry_over_decided_at']);
        });
    }
};
