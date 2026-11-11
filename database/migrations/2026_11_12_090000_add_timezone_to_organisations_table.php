<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timestamps stay stored in UTC. The organisation's timezone decides what "today" means
     * (clock-in date, recurring plans, carry-over, close time) and how times are displayed.
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
