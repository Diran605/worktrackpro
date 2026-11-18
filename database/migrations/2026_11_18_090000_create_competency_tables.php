<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Competency & Readiness: practical checks per product, people's answers, and the reviews.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['manage_competency', 'review_competency'];

    public function up(): void
    {
        // A practical task, e.g. "A client says a payment failed but money left their account. Show how you'd fix it."
        Schema::create('competency_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained('systems')->cascadeOnDelete();
            $table->string('title');
            $table->text('scenario');
            $table->json('criteria');                         // ["Checks the payment provider's log", ...]
            $table->unsignedTinyInteger('weight')->default(1); // 1 standard, 2 important, 3 essential
            $table->boolean('is_required')->default(true);      // required (for required_for) or optional practice
            $table->json('required_for')->nullable();          // {everyone: bool, roles: [], departments: [], users: []}
            $table->unsignedSmallInteger('valid_for_days')->nullable(); // null: never expires
            $table->json('section_ids')->nullable();           // manual sections to study first
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['system_id', 'is_active']);
        });

        // One person's answer. A new answer (after expiry or a major manual change) is a new row.
        Schema::create('competency_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft');   // draft | submitted | changes_requested | approved
            $table->text('answer')->nullable();
            $table->json('video_links')->nullable();
            $table->unsignedSmallInteger('times_submitted')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->string('manual_version')->nullable();      // the product's manual version when approved
            $table->timestamp('expires_at')->nullable();
            $table->boolean('needs_recheck')->default(false);   // a major manual version came out after approval
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['competency_check_id', 'status']);
        });

        Schema::create('competency_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_submission_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        // Every decision on an answer, so people see the whole conversation.
        Schema::create('competency_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30);                     // approved | changes_requested
            $table->json('criteria_met')->nullable();           // indexes of the criteria ticked
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // A daily plan task can be "Pass the competency check: …".
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('competency_check_id')->nullable()->after('manual_change_request_id')
                ->constrained('competency_checks')->nullOnDelete();
        });

        // Organisations with the Knowledge Base get the new module, as every module was given before.
        $now = now();
        $withKnowledge = DB::table('organisation_modules')->where('module', 'knowledge')->pluck('organisation_id');
        foreach ($withKnowledge as $organisationId) {
            DB::table('organisation_modules')->insertOrIgnore([
                'organisation_id' => $organisationId,
                'module' => 'competency',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // New permissions for admins and super admins (the roles seeder does the same on fresh installs).
        $tables = config('permission.table_names');
        $roleIds = DB::table($tables['roles'])->whereIn('name', ['admin', 'super_admin'])->pluck('id');
        foreach (self::PERMISSIONS as $name) {
            $id = DB::table($tables['permissions'])->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table($tables['permissions'])->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            foreach ($roleIds as $roleId) {
                DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('competency_check_id');
        });
        Schema::dropIfExists('competency_reviews');
        Schema::dropIfExists('competency_evidence');
        Schema::dropIfExists('competency_submissions');
        Schema::dropIfExists('competency_checks');
        DB::table('organisation_modules')->where('module', 'competency')->delete();

        $tables = config('permission.table_names');
        DB::table($tables['permissions'])->whereIn('name', self::PERMISSIONS)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
