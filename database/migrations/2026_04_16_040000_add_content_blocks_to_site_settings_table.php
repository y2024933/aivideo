<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('about_content')->nullable()->after('hero_content');
            $table->json('service_content')->nullable()->after('about_content');
            $table->json('contact_content')->nullable()->after('service_content');
            $table->json('social_links')->nullable()->after('contact_content');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'about_content',
                'service_content',
                'contact_content',
                'social_links',
            ]);
        });
    }
};
