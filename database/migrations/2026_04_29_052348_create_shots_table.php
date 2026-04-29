<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->string('shot_id', 8);
            $table->tinyInteger('shot_order');

            $table->decimal('duration_seconds', 4, 1)->nullable();
            $table->text('scene_description')->nullable();
            $table->string('camera_movement')->nullable();
            $table->text('voiceover_text')->nullable();
            $table->text('subtitle')->nullable();
            $table->string('emotion', 64)->nullable();

            $table->text('flux_prompt');
            $table->text('kling_prompt')->nullable();

            // 圖片
            $table->string('image_url', 512)->nullable();
            $table->string('image_request_id', 128)->nullable();
            $table->string('image_status', 16)->default('pending');
            $table->tinyInteger('image_retry_count')->default(0);
            $table->decimal('image_cost_usd', 10, 4)->default(0);

            // 影片
            $table->string('video_url', 512)->nullable();
            $table->string('video_request_id', 128)->nullable();
            $table->string('video_status', 16)->default('pending');
            $table->tinyInteger('video_retry_count')->default(0);
            $table->decimal('video_cost_usd', 10, 4)->default(0);
            $table->text('video_error')->nullable();

            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('building_cases')->cascadeOnDelete();
            $table->unique(['case_id', 'shot_id']);
            $table->index(['video_status', 'video_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
