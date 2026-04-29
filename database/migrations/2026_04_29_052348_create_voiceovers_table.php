<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voiceovers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->text('text');
            $table->string('voice_id', 128)->nullable();
            $table->string('audio_url', 512)->nullable();
            $table->decimal('duration_seconds', 4, 1)->nullable();
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->string('status', 16)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('building_cases')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voiceovers');
    }
};
