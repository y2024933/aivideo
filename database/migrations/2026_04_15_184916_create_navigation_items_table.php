<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->foreignId('parent_id')->nullable()->index();
            $table->foreignId('page_id')->nullable()->index();
            $table->string('position')->default('primary');
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('target')->default('_self');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
