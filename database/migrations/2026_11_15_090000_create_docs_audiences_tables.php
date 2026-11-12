<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audiences for the documentation library: groups of readers such as "Teachers" or "Finance".
 * Chapters can be meant for one or more audiences; access codes say which audiences they open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docs_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organisation_id', 'slug']);
        });

        // Which audiences a chapter is meant for (none = everyone who can open the manual).
        Schema::create('manual_section_audience', function (Blueprint $table) {
            $table->foreignId('manual_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audience_id')->constrained('docs_audiences')->cascadeOnDelete();

            $table->primary(['manual_section_id', 'audience_id']);
        });

        // Which audiences an access code opens, per product.
        Schema::create('docs_access_code_audiences', function (Blueprint $table) {
            $table->foreignId('grant_id')->constrained('docs_access_code_systems')->cascadeOnDelete();
            $table->foreignId('audience_id')->constrained('docs_audiences')->cascadeOnDelete();

            $table->primary(['grant_id', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_access_code_audiences');
        Schema::dropIfExists('manual_section_audience');
        Schema::dropIfExists('docs_audiences');
    }
};
