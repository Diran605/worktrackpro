<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('work_session_id')->nullable()->after('daily_plan_id')
                ->constrained('work_sessions')->nullOnDelete();
            $table->string('stop_reason')->default('manual')->after('duration_minutes');
            $table->boolean('is_verified')->default(true)->after('stop_reason');

            $table->index(['work_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['work_session_id']);
            $table->dropForeign(['work_session_id']);
            $table->dropColumn(['work_session_id', 'stop_reason', 'is_verified']);
        });
    }
};

