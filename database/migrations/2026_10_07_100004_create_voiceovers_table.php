<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voiceovers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            // 刻意命名 shot_uuid：shots.shot_id 是 'S01' 這種字串標籤，避免混淆
            $table->foreignUuid('shot_uuid')->nullable()->constrained('shots')->nullOnDelete();
            $table->text('text');
            $table->string('voice_id', 128)->nullable();
            $table->string('audio_url', 512)->nullable();
            $table->string('remote_url', 1024)->nullable();
            $table->decimal('duration_seconds', 5, 1)->nullable();
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->enum('status', ['pending', 'done', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voiceovers');
    }
};
