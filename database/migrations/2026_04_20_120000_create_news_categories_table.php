<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 建立 news_categories 表
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. 在 news_articles 表新增 nullable FK 欄位
        Schema::table('news_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('news_category_id')->nullable()->after('category');
        });

        // 3. 搬資料：從 news_articles 讀取 distinct (site_id, category) 建立 news_categories 記錄
        $distinctCategories = DB::table('news_articles')
            ->select('site_id', 'category')
            ->distinct()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->get();

        foreach ($distinctCategories as $row) {
            DB::table('news_categories')->insert([
                'site_id' => $row->site_id,
                'name' => $row->category,
                'slug' => Str::slug($row->category),
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 回填 news_category_id
        $allCategories = DB::table('news_categories')->get();
        foreach ($allCategories as $cat) {
            DB::table('news_articles')
                ->where('site_id', $cat->site_id)
                ->where('category', $cat->name)
                ->update(['news_category_id' => $cat->id]);
        }

        // 4. 清理
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreign('news_category_id')->references('id')->on('news_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // 還原 news_articles.category 欄位
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropForeign(['news_category_id']);
            $table->string('category')->nullable()->after('slug');
        });

        // 回填 category 文字
        $allCategories = DB::table('news_categories')->get();
        foreach ($allCategories as $cat) {
            DB::table('news_articles')
                ->where('news_category_id', $cat->id)
                ->update(['category' => $cat->name]);
        }

        // 移除 news_category_id 欄位與 news_categories 表
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn('news_category_id');
        });

        Schema::dropIfExists('news_categories');
    }
};
