<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registry of internal systems / projects.
        Schema::create('systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('summary', 500)->nullable();          // one line: what it does
            $table->longText('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('criticality')->default('medium');      // low | medium | high | critical
            $table->string('status')->default('active');           // active | deprecated | retired
            $table->string('url')->nullable();
            $table->text('support_contact')->nullable();           // how to get help when it breaks
            $table->text('escalation_notes')->nullable();
            $table->string('manual_version')->default('0.0');      // bumped on every approved manual change
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'slug']);
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('system_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role'); // owner | backup_owner | reviewer | contact
            $table->timestamps();

            $table->unique(['system_id', 'user_id', 'role']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_members');
        Schema::dropIfExists('systems');
    }
};
