<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 建案資料
            $table->string('name');
            $table->string('builder_name')->nullable();
            $table->string('location')->nullable();
            $table->string('area_range', 64)->nullable();
            $table->string('price_range', 64)->nullable();
            $table->string('target_audience', 32)->nullable();
            $table->string('tone', 32)->nullable();
            $table->smallInteger('video_length_seconds')->default(60);
            $table->json('platforms')->nullable();

            // 角色
            $table->text('character_dna')->nullable();
            $table->string('character_nickname', 64)->nullable();
            $table->uuid('approved_character_id')->nullable();

            // 故事
            $table->text('story_outline')->nullable();
            $table->text('must_have')->nullable();
            $table->text('taboos')->nullable();

            // 狀態
            $table->string('status', 64)->default('draft');
            $table->text('status_message')->nullable();

            // AI 輸出
            $table->json('script_v1')->nullable();
            $table->json('script_v2')->nullable();
            $table->json('reviews')->nullable();
            $table->uuid('voiceover_id')->nullable();

            // 成本追蹤
            $table->decimal('cost_usd', 10, 4)->default(0);

            // 交付
            $table->string('drive_folder_url', 512)->nullable();
            $table->string('final_video_url', 512)->nullable();

            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_cases');
    }
};
