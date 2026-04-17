<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->foreignId('project_id')->nullable()->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('reported_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_updates');
    }
};
