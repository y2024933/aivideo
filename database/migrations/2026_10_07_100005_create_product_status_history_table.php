<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_status_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64);
            $table->string('triggered_by', 16);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_status_history');
    }
};
