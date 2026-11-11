<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('work_type_id')->nullable()->after('work_type')->constrained('work_types')->nullOnDelete();
        });

        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('project_client_id')->nullable()->after('project_client')->constrained('project_clients')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_type_id');
        });

        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_client_id');
            $table->dropConstrainedForeignId('assigned_by');
        });
    }
};
