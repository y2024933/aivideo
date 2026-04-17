<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('brand_name')->nullable();
            $table->string('primary_domain')->nullable()->unique();
            $table->string('theme_key')->default('builder-classic');
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('has_news')->default(true);
            $table->boolean('has_progress')->default(true);
            $table->boolean('has_contact_form')->default(true);
            $table->boolean('has_projects')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
