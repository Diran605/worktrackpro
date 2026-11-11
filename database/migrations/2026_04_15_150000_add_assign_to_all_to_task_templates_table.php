<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->boolean('assign_to_all')->default(false)->after('department_id');
            $table->index(['organisation_id', 'assign_to_all']);
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'assign_to_all']);
            $table->dropColumn('assign_to_all');
        });
    }
};

