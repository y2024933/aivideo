<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_cases', function (Blueprint $table) {
            $table->string('render_id', 128)->nullable()->after('final_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('building_cases', function (Blueprint $table) {
            $table->dropColumn('render_id');
        });
    }
};
