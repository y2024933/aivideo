<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('case_id');
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->string('triggered_by', 16);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('case_id')->references('id')->on('building_cases')->cascadeOnDelete();
            $table->index(['case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_status_history');
    }
};
