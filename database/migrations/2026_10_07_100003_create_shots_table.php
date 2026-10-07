<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('shot_id', 8);
            $table->tinyInteger('shot_order');
            $table->enum('role', ['hook', 'pain', 'feature', 'proof', 'cta', 'other'])->nullable();
            $table->decimal('duration_seconds', 4, 1)->nullable();
            $table->text('scene_description')->nullable();

            // 文字
            $table->text('subtitle')->nullable();
            $table->boolean('subtitle_has_simplified')->default(false)->index();
            $table->text('voiceover_text')->nullable();
            $table->boolean('voiceover_has_simplified')->default(false);
            $table->json('compliance_flags')->nullable();

            // 視覺
            $table->string('transition', 32)->nullable();
            $table->string('ken_burns', 32)->nullable();
            $table->enum('fit', ['contain', 'cover'])->default('contain');
            $table->foreignUuid('product_image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $table->string('image_url', 512)->nullable();
            // Remotion Lambda 的唯一圖片來源，必須是可公開存取的絕對 URL
            $table->string('image_remote_url', 1024)->nullable();

            // B-roll 影片
            $table->enum('video_provider', ['none', 'kling', 'dola'])->default('none');
            $table->text('video_prompt')->nullable();
            $table->string('video_url', 512)->nullable();
            $table->string('video_remote_url', 1024)->nullable();
            $table->string('video_request_id', 128)->nullable();
            $table->enum('video_status', ['pending', 'processing', 'done', 'failed', 'skipped'])->default('skipped');
            $table->tinyInteger('video_retry_count')->default(0);
            $table->decimal('video_cost_usd', 10, 4)->default(0);
            $table->text('video_error')->nullable();

            // 配音
            $table->string('voiceover_url', 512)->nullable();
            $table->string('voiceover_remote_url', 1024)->nullable();
            $table->enum('voiceover_status', ['pending', 'processing', 'done', 'failed', 'skipped'])->default('skipped');
            $table->string('voiceover_voice_id', 128)->nullable();
            $table->decimal('voiceover_duration_sec', 4, 1)->nullable();

            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'shot_id']);
            $table->index(['video_status', 'video_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};
