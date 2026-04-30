<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->text('flux_prompt')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->text('flux_prompt')->nullable(false)->change();
        });
    }
};
