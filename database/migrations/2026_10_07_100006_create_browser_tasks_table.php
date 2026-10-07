<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', [
                'shopee_scrape_product',
                'resolve_short_link',
                'external_image_scrape',
                'shopee_session_check',
                'shopee_publish_draft',
                'dola_generate_video',
            ]);
            $table->string('subject_type', 255)->nullable();
            $table->char('subject_id', 36)->nullable();
            $table->enum('status', ['pending', 'running', 'succeeded', 'degraded', 'failed', 'needs_manual'])->default('pending');
            $table->tinyInteger('attempts')->default(0);
            $table->tinyInteger('max_attempts')->default(3);
            $table->enum('strategy_used', ['xhr', 'dom', 'manual'])->nullable();
            $table->string('profile', 64)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('screenshot_path', 1024)->nullable();
            $table->string('trace_path', 1024)->nullable();
            $table->string('har_path', 1024)->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_tasks');
    }
};
