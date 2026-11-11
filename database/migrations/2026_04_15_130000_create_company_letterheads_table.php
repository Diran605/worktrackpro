<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_letterheads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('header_image_path')->nullable();
            $table->string('footer_image_path')->nullable();
            $table->integer('header_height_px')->default(0);
            $table->integer('footer_height_px')->default(0);
            $table->string('accent_color', 7)->default('#0d9488');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organisation_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_letterheads');
    }
};

