<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core roles (super_admin, admin, worker) stay global with organisation_id = null.
     * Custom roles created by an organisation's admins belong to that organisation only.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id')
                ->constrained('organisations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
        });
    }
};
