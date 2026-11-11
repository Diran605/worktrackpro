<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The weekly stats cache was never written or read; stats are calculated live by StatsService.
     */
    public function up(): void
    {
        Schema::dropIfExists('weekly_stats_cache');
    }

    public function down(): void
    {
        Schema::create('weekly_stats_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->date('week_start');
            $table->integer('total_planned');
            $table->integer('total_completed_planned');
            $table->decimal('execution_rate', 5, 2);
            $table->integer('direct_minutes');
            $table->integer('indirect_minutes');
            $table->integer('growth_minutes');
            $table->integer('unplanned_count');
            $table->integer('total_log_count');
            $table->timestamp('recalculated_at');
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
        });
    }
};
