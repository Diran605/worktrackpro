<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('message_type'); // direct, broadcast, system, letter, reopen_request, reopen_response
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'created_at']);
            $table->index(['message_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

