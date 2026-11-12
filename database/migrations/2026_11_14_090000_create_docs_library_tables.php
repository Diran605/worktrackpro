<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Phase 3B: the public documentation library.
     */
    public function up(): void
    {
        // Groups systems on the library home page (like "Secure Networking" on docs.fortinet.com).
        Schema::create('system_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description', 500)->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
        });

        Schema::table('systems', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('department_id')
                ->constrained('system_categories')->nullOnDelete();
            // hidden = not in the library (staff portal only) | public | restricted (staff + access codes)
            $table->string('library_visibility')->default('hidden')->after('status');
        });

        Schema::table('manual_sections', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            // inherit = same as the manual | restricted = staff + access codes that include restricted chapters
            $table->string('visibility')->default('inherit')->after('status');
        });

        // Versions are published by the system's owner; approved changes wait as "upcoming" until then.
        Schema::create('manual_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->string('version');
            $table->boolean('is_major')->default(false);
            $table->text('summary')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['system_id', 'published_at']);
        });

        Schema::table('manual_section_revisions', function (Blueprint $table) {
            $table->foreignId('release_id')->nullable()->after('manual_version')
                ->constrained('manual_releases')->nullOnDelete();
        });

        Schema::create('docs_access_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->text('code');                         // encrypted, so admins can copy it again
            $table->string('code_hash', 64)->unique();    // for lookup when a reader enters it
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('docs_access_code_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_code_id')->constrained('docs_access_codes')->cascadeOnDelete();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->boolean('include_restricted')->default(false);
            $table->timestamps();

            $table->unique(['access_code_id', 'system_id']);
        });

        Schema::create('docs_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('docs_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_section_id')->constrained('manual_sections')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->text('comment')->nullable();
            $table->string('visitor_hash', 64);
            $table->timestamps();

            $table->unique(['manual_section_id', 'visitor_hash']);
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->string('docs_domain')->nullable()->unique()->after('slug');
            $table->string('docs_title')->nullable()->after('docs_domain');
            $table->string('docs_tagline', 500)->nullable()->after('docs_title');
            $table->json('docs_footer_links')->nullable()->after('docs_tagline');
            $table->json('docs_banners')->nullable()->after('docs_footer_links');
        });

        $this->backfill();

        Schema::table('manual_sections', function (Blueprint $table) {
            $table->unique(['system_id', 'slug']);
        });
    }

    /**
     * Readable addresses for existing sections, and one "Initial release" per system with content.
     */
    private function backfill(): void
    {
        DB::table('manual_sections')->orderBy('id')->get(['id', 'system_id', 'title'])
            ->groupBy('system_id')
            ->each(function ($sections) {
                $used = [];
                foreach ($sections as $section) {
                    $base = Str::slug($section->title) ?: 'section';
                    $slug = $base;
                    $i = 2;
                    while (in_array($slug, $used, true) || in_array($slug, ['changes', 'pdf', 'request-access'], true)) {
                        $slug = "{$base}-{$i}";
                        $i++;
                    }
                    $used[] = $slug;
                    DB::table('manual_sections')->where('id', $section->id)->update(['slug' => $slug]);
                }
            });

        DB::table('systems')->get(['id', 'manual_version'])->each(function ($system) {
            $revisionIds = DB::table('manual_section_revisions')
                ->join('manual_sections', 'manual_sections.id', '=', 'manual_section_revisions.manual_section_id')
                ->where('manual_sections.system_id', $system->id)
                ->pluck('manual_section_revisions.id');

            if ($revisionIds->isEmpty()) {
                return;
            }

            $version = $system->manual_version && $system->manual_version !== '0.0' ? $system->manual_version : '1.0';

            $releaseId = DB::table('manual_releases')->insertGetId([
                'system_id' => $system->id,
                'version' => $version,
                'is_major' => false,
                'summary' => 'Initial release',
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('manual_section_revisions')->whereIn('id', $revisionIds)->update(['release_id' => $releaseId]);
            DB::table('systems')->where('id', $system->id)->update(['manual_version' => $version]);
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['docs_domain']);
            $table->dropColumn(['docs_domain', 'docs_title', 'docs_tagline', 'docs_footer_links', 'docs_banners']);
        });

        Schema::dropIfExists('docs_feedback');
        Schema::dropIfExists('docs_access_requests');
        Schema::dropIfExists('docs_access_code_systems');
        Schema::dropIfExists('docs_access_codes');

        Schema::table('manual_section_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('release_id');
        });
        Schema::dropIfExists('manual_releases');

        Schema::table('manual_sections', function (Blueprint $table) {
            $table->dropUnique(['system_id', 'slug']);
            $table->dropColumn(['slug', 'visibility']);
        });

        Schema::table('systems', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('library_visibility');
        });

        Schema::dropIfExists('system_categories');
    }
};
