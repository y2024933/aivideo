<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('surroundings')->nullable()->after('gallery');
            $table->json('area_description')->nullable()->after('surroundings');
            $table->json('sales_info')->nullable()->after('area_description');
            $table->json('site_visit_gallery')->nullable()->after('sales_info');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['surroundings', 'area_description', 'sales_info', 'site_visit_gallery']);
        });
    }
};
