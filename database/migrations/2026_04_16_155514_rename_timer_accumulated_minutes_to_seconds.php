<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing minutes to seconds first
        \Illuminate\Support\Facades\DB::table('daily_plans')
            ->update(['timer_accumulated_minutes' => \Illuminate\Support\Facades\DB::raw('timer_accumulated_minutes * 60')]);

        Schema::table('daily_plans', function (Blueprint $table) {
            $table->renameColumn('timer_accumulated_minutes', 'timer_accumulated_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->renameColumn('timer_accumulated_seconds', 'timer_accumulated_minutes');
        });

        \Illuminate\Support\Facades\DB::table('daily_plans')
            ->update(['timer_accumulated_minutes' => \Illuminate\Support\Facades\DB::raw('timer_accumulated_minutes / 60')]);
    }
};
