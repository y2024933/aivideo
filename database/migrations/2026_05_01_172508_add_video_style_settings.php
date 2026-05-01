<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_cases', function (Blueprint $table) {
            $table->json('subtitle_settings')->nullable()->after('review_meta');
            $table->string('global_transition', 32)->default('crossfade')->after('subtitle_settings');
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->string('transition', 32)->nullable()->after('emotion');
        });
    }

    public function down(): void
    {
        Schema::table('building_cases', function (Blueprint $table) {
            $table->dropColumn(['subtitle_settings', 'global_transition']);
        });

        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn('transition');
        });
    }
};
