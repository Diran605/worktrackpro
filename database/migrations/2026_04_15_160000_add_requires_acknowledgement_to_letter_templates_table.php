<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_templates', function (Blueprint $table) {
            $table->boolean('requires_acknowledgement')->default(false)->after('is_system_default');
            $table->index(['requires_acknowledgement']);
        });
    }

    public function down(): void
    {
        Schema::table('letter_templates', function (Blueprint $table) {
            $table->dropIndex(['requires_acknowledgement']);
            $table->dropColumn('requires_acknowledgement');
        });
    }
};

