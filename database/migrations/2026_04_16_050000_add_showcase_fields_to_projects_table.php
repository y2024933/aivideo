<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_category')->nullable()->after('status');
            $table->string('tagline')->nullable()->after('launch_year');
            $table->string('address')->nullable()->after('location');
            $table->string('households')->nullable()->after('area');
            $table->string('floors')->nullable()->after('households');
            $table->string('layout_plan')->nullable()->after('floors');
            $table->json('project_features')->nullable()->after('layout_plan');
            $table->json('gallery')->nullable()->after('featured_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'project_category',
                'tagline',
                'address',
                'households',
                'floors',
                'layout_plan',
                'project_features',
                'gallery',
            ]);
        });
    }
};
