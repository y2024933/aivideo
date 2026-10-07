<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(0);
            $table->enum('source', ['shopee', 'upload', 'external'])->default('shopee');
            $table->string('source_url', 1024)->nullable();
            $table->string('image_hash', 128)->nullable();
            $table->string('local_path', 512)->nullable();
            $table->string('remote_url', 1024)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('mime', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_selected')->default(true);
            $table->enum('status', ['pending', 'done', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            // 圖片授權狀態（著作權風險控管）
            $table->enum('license_status', ['unverified', 'seller_authorized', 'own_shot', 'platform_provided'])->default('unverified');
            $table->string('license_note', 512)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'image_hash']);
            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
