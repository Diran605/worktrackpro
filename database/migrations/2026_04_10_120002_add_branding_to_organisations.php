<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('primary_color', 30)->default('#0d9488')->after('is_active');
            $table->string('secondary_color', 30)->default('#6366f1')->after('primary_color');
            $table->string('logo')->nullable()->after('secondary_color');
            $table->string('letterhead')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'logo', 'letterhead']);
        });
    }
};
