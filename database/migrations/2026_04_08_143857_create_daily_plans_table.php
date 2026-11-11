<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->date('date');
            $table->string('task_name');
            $table->string('project_client')->nullable();
            $table->string('priority')->default('medium'); // enum handled at model level
            $table->unsignedInteger('expected_duration_minutes');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // enum handled at model level
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
