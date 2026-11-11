<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->after('id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('department_id')->after('organisation_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->after('password')->default(true);
            $table->timestamp('last_login_at')->after('is_active')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['organisation_id', 'department_id', 'is_active', 'last_login_at', 'deleted_at']);
        });
    }
};
