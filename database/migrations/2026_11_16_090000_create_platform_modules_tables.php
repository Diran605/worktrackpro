<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modules each organisation has, and platform-wide settings (company mode, main organisation).
 */
return new class extends Migration
{
    /** Module keys at the time of this migration (see App\Support\Modules). */
    private const MODULES = ['work_tracking', 'attendance', 'letters', 'knowledge', 'docs_library'];

    public function up(): void
    {
        Schema::create('organisation_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->timestamps();

            $table->unique(['organisation_id', 'module']);
        });

        // With both Attendance and Work Tracking: is clocking in tied to daily plans? (today: yes)
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->boolean('link_attendance_to_work')->default(true)->after('abandoned_session_close_time');
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Existing organisations keep everything they have today.
        $now = now();
        foreach (DB::table('organisations')->pluck('id') as $organisationId) {
            foreach (self::MODULES as $module) {
                DB::table('organisation_modules')->insert([
                    'organisation_id' => $organisationId,
                    'module' => $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // One active organisation → single company mode; several → keep them all visible (multi).
        $active = DB::table('organisations')->where('is_active', true)->whereNull('deleted_at')->orderBy('id')->pluck('id');
        $settings = [
            'company_mode' => $active->count() <= 1 ? 'single' : 'multi',
            'main_organisation_id' => $active->first(),
        ];
        foreach ($settings as $key => $value) {
            DB::table('platform_settings')->insert([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('organisation_modules');
        Schema::table('organisation_settings', function (Blueprint $table) {
            $table->dropColumn('link_attendance_to_work');
        });
    }
};
