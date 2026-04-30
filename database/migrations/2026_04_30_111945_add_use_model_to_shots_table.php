<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->string('use_model', 128)->nullable()->after('kling_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn('use_model');
        });
    }
};
