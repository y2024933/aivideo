<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->string('video_remote_url', 1024)->nullable()->after('video_url');
            $table->string('voiceover_remote_url', 1024)->nullable()->after('voiceover_voice_id');
        });
    }

    public function down(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn(['video_remote_url', 'voiceover_remote_url']);
        });
    }
};
