<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * video_provider 改成 nullable，null = 繼承上一層（鏡頭 → 商品 → config）。
 *
 * 原本兩欄都是 NOT NULL DEFAULT 'none'，等於「每個鏡頭都明確指定了 none」，
 * 三層優先序根本生不出來 —— 商品設 kling 也會被鏡頭的 none 蓋掉。
 *
 * 用原生 SQL 而不是 Blueprint::change()：本專案只跑 MySQL，而 doctrine/dbal 不認
 * enum 欄位，change() 會丟 Unknown database type "enum" requested。
 */
return new class extends Migration
{
    private const ENUM = "ENUM('none','kling','dola')";

    public function up(): void
    {
        foreach (['products', 'shots'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY video_provider " . self::ENUM . ' NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        foreach (['products', 'shots'] as $table) {
            DB::statement("UPDATE {$table} SET video_provider = 'none' WHERE video_provider IS NULL");
            DB::statement("ALTER TABLE {$table} MODIFY video_provider " . self::ENUM . " NOT NULL DEFAULT 'none'");
        }
    }
};
