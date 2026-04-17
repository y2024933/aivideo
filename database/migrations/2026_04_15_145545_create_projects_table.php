<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('planning');
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('launch_year')->nullable();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('area')->nullable();
            $table->string('featured_image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
