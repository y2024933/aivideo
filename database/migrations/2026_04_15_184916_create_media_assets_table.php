<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->string('name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('folder')->nullable();
            $table->string('usage_type')->default('general');
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
