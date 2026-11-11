<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();

            $table->unsignedInteger('abandoned_timer_hours')->default(3);
            $table->unsignedInteger('carry_over_flag_threshold')->default(3);

            $table->unsignedInteger('inbox_max_attachment_kb')->default(5120);
            $table->json('inbox_allowed_mime_types')->nullable();

            $table->string('abandoned_session_close_time')->default('20:00');

            $table->timestamps();

            $table->unique(['organisation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_settings');
    }
};

