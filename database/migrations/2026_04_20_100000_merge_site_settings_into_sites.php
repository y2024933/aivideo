<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 在 sites 表新增 9 個 nullable JSON 欄位
        Schema::table('sites', function (Blueprint $table) {
            $table->json('homepage_sections')->nullable();
            $table->json('hero_content')->nullable();
            $table->json('about_content')->nullable();
            $table->json('service_content')->nullable();
            $table->json('contact_content')->nullable();
            $table->json('social_links')->nullable();
            $table->json('seo_defaults')->nullable();
            $table->json('footer_content')->nullable();
            $table->json('tracking')->nullable();
        });

        // 2. 從 site_settings 搬資料到 sites（JOIN UPDATE）
        DB::statement('
            UPDATE sites
            INNER JOIN site_settings ON site_settings.site_id = sites.id
            SET
                sites.homepage_sections = site_settings.homepage_sections,
                sites.hero_content = site_settings.hero_content,
                sites.about_content = site_settings.about_content,
                sites.service_content = site_settings.service_content,
                sites.contact_content = site_settings.contact_content,
                sites.social_links = site_settings.social_links,
                sites.seo_defaults = site_settings.seo_defaults,
                sites.footer_content = site_settings.footer_content
        ');

        // 3. 刪除 sites 表的 contact_email 和 contact_phone 欄位
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'contact_phone']);
        });

        // 4. Drop site_settings 表
        Schema::dropIfExists('site_settings');
    }

    public function down(): void
    {
        // 1. 重建 site_settings 表
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->json('homepage_sections')->nullable();
            $table->json('hero_content')->nullable();
            $table->json('about_content')->nullable();
            $table->json('service_content')->nullable();
            $table->json('contact_content')->nullable();
            $table->json('social_links')->nullable();
            $table->json('seo_defaults')->nullable();
            $table->json('footer_content')->nullable();
            $table->timestamps();
        });

        // 2. 在 sites 表補回 contact_email 和 contact_phone
        Schema::table('sites', function (Blueprint $table) {
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
        });

        // 3. 從 sites 搬資料回 site_settings
        DB::statement('
            INSERT INTO site_settings (site_id, homepage_sections, hero_content, about_content, service_content, contact_content, social_links, seo_defaults, footer_content, created_at, updated_at)
            SELECT id, homepage_sections, hero_content, about_content, service_content, contact_content, social_links, seo_defaults, footer_content, NOW(), NOW()
            FROM sites
            WHERE homepage_sections IS NOT NULL OR hero_content IS NOT NULL
        ');

        // 4. 從 footer_content 回填 contact_email 和 contact_phone
        DB::statement("
            UPDATE sites
            SET contact_email = JSON_UNQUOTE(JSON_EXTRACT(footer_content, '$.email')),
                contact_phone = JSON_UNQUOTE(JSON_EXTRACT(footer_content, '$.phone'))
            WHERE footer_content IS NOT NULL
        ");

        // 5. 移除 sites 表的 JSON 欄位
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'homepage_sections', 'hero_content', 'about_content',
                'service_content', 'contact_content', 'social_links',
                'seo_defaults', 'footer_content', 'tracking',
            ]);
        });
    }
};
