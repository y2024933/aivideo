<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->text('prompt');
            $table->string('image_url', 512)->nullable();
            $table->string('fal_request_id', 128)->nullable();
            $table->string('status', 16)->default('pending');
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('building_cases')->cascadeOnDelete();
            $table->index(['case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_options');
    }
};
