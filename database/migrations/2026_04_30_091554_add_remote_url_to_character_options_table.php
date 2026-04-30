<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_options', function (Blueprint $table) {
            $table->string('remote_url', 512)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('character_options', function (Blueprint $table) {
            $table->dropColumn('remote_url');
        });
    }
};
