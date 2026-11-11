<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timer_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')->constrained('daily_plans')->cascadeOnDelete();
            $table->timestamp('paused_at');
            $table->timestamp('resumed_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();

            $table->index(['daily_plan_id', 'paused_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timer_pauses');
    }
};

