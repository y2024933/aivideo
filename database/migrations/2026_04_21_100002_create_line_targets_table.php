<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_channel_id')->constrained('line_channels')->cascadeOnDelete();
            $table->string('type'); // group, user
            $table->string('line_id');
            $table->string('display_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['line_channel_id', 'line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_targets');
    }
};
