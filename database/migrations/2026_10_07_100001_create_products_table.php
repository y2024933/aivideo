<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 來源
            $table->enum('source', ['shopee_link', 'manual', 'external_link'])->default('manual')->index();
            $table->string('source_url', 1024)->nullable();
            $table->unsignedBigInteger('shopee_shop_id')->nullable();
            $table->unsignedBigInteger('shopee_item_id')->nullable();
            $table->string('affiliate_url', 1024)->nullable();
            $table->unique(['shopee_shop_id', 'shopee_item_id']);

            // 商品資料
            $table->string('title', 512);
            $table->boolean('title_has_simplified')->default(false)->index();
            $table->string('brand', 255)->nullable();
            $table->string('category', 255)->nullable()->index();
            $table->json('category_path')->nullable();
            $table->string('compliance_profile', 64)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('price_min', 12, 2)->nullable();
            $table->decimal('price_max', 12, 2)->nullable();
            $table->decimal('price_before_discount', 12, 2)->nullable();
            $table->char('currency', 3)->default('TWD');
            $table->decimal('rating_star', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->unsignedInteger('historical_sold')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->text('description')->nullable();
            $table->json('variations')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('scraped_at')->nullable();

            // 狀態
            $table->string('status', 64)->default('draft')->index();
            $table->text('status_message')->nullable();
            $table->text('needs_manual_reason')->nullable();

            // 影片設定
            $table->smallInteger('video_length_seconds')->default(30);
            $table->enum('audio_mode', ['none', 'tts', 'bgm_only'])->default('none');
            $table->string('voice_id_preferred', 64)->nullable();
            $table->string('bgm_url', 512)->nullable();
            $table->string('bgm_remote_url', 1024)->nullable();
            $table->decimal('bgm_volume', 3, 2)->default(0.25);
            $table->string('bgm_license_note', 512)->nullable();
            $table->enum('video_provider', ['none', 'kling', 'dola'])->default('none');
            $table->json('subtitle_settings')->nullable();
            $table->string('global_transition', 32)->default('crossfade');
            $table->string('default_ken_burns', 32)->default('auto');

            // LLM 產出
            // null = 用 config('services.script_provider') 的全域預設（App\Enums\ScriptProvider）
            $table->string('script_provider', 32)->nullable();
            $table->json('script')->nullable();
            $table->string('script_model', 64)->nullable();
            $table->timestamp('script_generated_at')->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('disclosure_prefix', 255)->nullable();

            // 合規
            $table->json('compliance_report')->nullable();
            $table->boolean('compliance_passed')->default(false)->index();
            $table->timestamp('compliance_checked_at')->nullable();
            $table->string('compliance_rules_version', 32)->nullable()->index();
            $table->string('compliance_rules_fingerprint', 16)->nullable()->index();

            // 成品
            $table->string('render_id', 128)->nullable();
            $table->string('final_video_url', 1024)->nullable();
            $table->string('final_video_remote_url', 1024)->nullable();
            $table->decimal('final_video_duration_sec', 5, 1)->nullable();

            // 上架
            $table->string('publish_platform', 32)->default('shopee_video');
            $table->string('publish_post_id', 128)->nullable();
            $table->string('publish_url', 1024)->nullable();
            $table->timestamp('published_at')->nullable();
            // 蝦皮 AI 規範要求標記「AI 生成影片」，發佈後不可修改，為上架的 blocking gate
            $table->boolean('ai_labeled')->default(false);

            // 成本
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->decimal('llm_cost_usd', 10, 4)->default(0);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
