<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('title');
            $table->string('work_type'); // direct, indirect, growth
            $table->unsignedInteger('expected_duration_minutes')->default(0);
            $table->string('recurrence_type'); // daily, weekly, one_time
            $table->tinyInteger('recurrence_day')->nullable(); // 0-6 (Sun-Sat) for weekly
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organisation_id', 'is_active']);
            $table->index(['recurrence_type', 'recurrence_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};

