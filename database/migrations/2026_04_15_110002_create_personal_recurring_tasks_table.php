<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_recurring_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('work_type'); // direct, indirect, growth
            $table->string('priority')->default('medium');
            $table->unsignedInteger('expected_duration_minutes')->default(0);
            $table->string('recurrence_type'); // daily, weekly
            $table->tinyInteger('recurrence_day')->nullable(); // 0-6 (Sun-Sat) for weekly
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['recurrence_type', 'recurrence_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_recurring_tasks');
    }
};

