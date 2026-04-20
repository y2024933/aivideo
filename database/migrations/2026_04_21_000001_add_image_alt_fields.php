<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->string('featured_image_alt')->nullable()->after('featured_image_path'));
        Schema::table('news_articles', fn (Blueprint $t) => $t->string('featured_image_alt')->nullable()->after('featured_image_path'));
        Schema::table('pages', fn (Blueprint $t) => $t->string('cover_image_alt')->nullable()->after('cover_image_path'));
        Schema::table('sites', fn (Blueprint $t) => $t->string('logo_alt')->nullable()->after('logo_path'));
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('featured_image_alt'));
        Schema::table('news_articles', fn (Blueprint $t) => $t->dropColumn('featured_image_alt'));
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('cover_image_alt'));
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn('logo_alt'));
    }
};
