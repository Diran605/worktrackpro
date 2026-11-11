<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organisations name their own work types ("Client calls", "Training"…). Each one belongs
     * to a category (direct / indirect / growth), which is what the productivity stats use.
     */
    public function up(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->string('category')->default('direct')->after('name');
        });

        Schema::table('daily_plans', function (Blueprint $table) {
            $table->foreignId('work_type_id')->nullable()->after('work_type')
                ->constrained('work_types')->nullOnDelete();
        });

        // Existing types named after a category get that category.
        foreach (['direct', 'indirect', 'growth'] as $category) {
            DB::table('work_types')->whereRaw('LOWER(TRIM(name)) = ?', [$category])->update(['category' => $category]);
        }

        // Organisations with no work types get the three defaults.
        $withTypes = DB::table('work_types')->whereNull('deleted_at')->distinct()->pluck('organisation_id');

        DB::table('organisations')->whereNotIn('id', $withTypes)->pluck('id')->each(function ($orgId) {
            foreach ([['Direct', 'direct', 'success'], ['Indirect', 'indirect', 'warning'], ['Growth', 'growth', 'info']] as [$name, $category, $color]) {
                DB::table('work_types')->insert([
                    'organisation_id' => $orgId,
                    'name' => $name,
                    'category' => $category,
                    'color' => $color,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_type_id');
        });

        Schema::table('work_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
