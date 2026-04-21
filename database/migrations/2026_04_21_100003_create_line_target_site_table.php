<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_target_site', function (Blueprint $table) {
            $table->foreignId('line_target_id')->constrained('line_targets')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->primary(['line_target_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_target_site');
    }
};
