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
            $table->string('voiceover_url', 512)->nullable()->after('video_error');
            $table->string('voiceover_status', 16)->default('pending')->after('voiceover_url');
            $table->string('voiceover_voice_id', 128)->nullable()->after('voiceover_status');
        });
    }

    public function down(): void
    {
        Schema::table('shots', function (Blueprint $table) {
            $table->dropColumn(['voiceover_url', 'voiceover_status', 'voiceover_voice_id']);
        });
    }
};
