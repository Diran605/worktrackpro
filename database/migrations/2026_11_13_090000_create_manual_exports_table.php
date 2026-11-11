<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated manual PDFs. An export is reused while nothing it contains has changed
     * (same fingerprint), so repeat downloads are instant.
     */
    public function up(): void
    {
        Schema::create('manual_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manual_version');
            $table->string('fingerprint', 64)->index();     // hash of everything that affects the PDF
            $table->json('section_ids')->nullable();          // null = the whole manual
            $table->json('options')->nullable();              // appendices etc.
            $table->string('status')->default('queued');      // queued | processing | ready | failed
            $table->string('file_path')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->json('meta')->nullable();                 // page map, merged PDFs, warnings
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['system_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_exports');
    }
};
