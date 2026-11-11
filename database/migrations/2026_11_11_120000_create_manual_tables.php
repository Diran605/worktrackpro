<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A manual is an ordered tree of sections. "number" (1, 1.2, 1.2.3) and "sort_key"
         * (0001.0002.0003) are recalculated after every structural change, so ordering and
         * numbering never need to be worked out at read time.
         */
        Schema::create('manual_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('manual_sections')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);     // order among siblings
            $table->string('number', 50)->nullable();             // e.g. "2.1.3"
            $table->string('sort_key', 255)->nullable()->index(); // e.g. "0002.0001.0003"
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('title');
            $table->string('kind')->default('written');           // written | uploaded_pdf
            $table->string('status')->default('published');       // published | archived
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->foreignId('last_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['system_id', 'parent_id', 'position']);
            $table->index(['system_id', 'status', 'sort_key']);
        });

        // Immutable history of each section's content.
        Schema::create('manual_section_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_section_id')->constrained('manual_sections')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body')->nullable();                 // written sections
            $table->string('file_path')->nullable();              // uploaded PDF sections
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            $table->string('change_summary', 500)->nullable();
            $table->string('manual_version')->nullable();         // the system's manual version this was published in
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['manual_section_id', 'version']);
        });

        Schema::table('manual_sections', function (Blueprint $table) {
            $table->foreign('current_revision_id')->references('id')->on('manual_section_revisions')->nullOnDelete();
        });

        // Proposed changes waiting for (or past) owner approval.
        Schema::create('manual_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->foreignId('manual_section_id')->nullable()->constrained('manual_sections')->nullOnDelete();
            $table->string('type');                                // create | edit | import
            $table->string('kind')->default('written');            // written | uploaded_pdf
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('page_from')->nullable();
            $table->unsignedInteger('page_to')->nullable();
            $table->json('payload')->nullable();                   // import: proposed section tree
            $table->foreignId('proposed_parent_id')->nullable()->constrained('manual_sections')->nullOnDelete();
            $table->foreignId('proposed_after_id')->nullable()->constrained('manual_sections')->nullOnDelete();
            $table->string('change_summary', 500)->nullable();
            $table->string('status')->default('draft');            // draft | submitted | changes_requested | approved | rejected
            $table->unsignedInteger('base_version')->nullable();   // section version the edit started from
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->boolean('is_major')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['system_id', 'status']);
            $table->index(['author_id', 'status']);
        });

        // Screenshots and images used inside written sections.
        Schema::create('manual_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Who has read which version of each section (drives study progress).
        Schema::create('manual_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manual_section_id')->constrained('manual_sections')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('read_at');

            $table->unique(['user_id', 'manual_section_id', 'version']);
        });

        // Documentation and study work is tracked through the normal plan + timer flow.
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('system_id')->nullable()->after('project_client_id')
                ->constrained('systems')->nullOnDelete();
            $table->foreignId('manual_change_request_id')->nullable()->after('system_id')
                ->constrained('manual_change_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_change_request_id');
            $table->dropConstrainedForeignId('system_id');
        });

        Schema::dropIfExists('manual_reads');
        Schema::dropIfExists('manual_assets');
        Schema::dropIfExists('manual_change_requests');

        Schema::table('manual_sections', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });

        Schema::dropIfExists('manual_section_revisions');
        Schema::dropIfExists('manual_sections');
    }
};
