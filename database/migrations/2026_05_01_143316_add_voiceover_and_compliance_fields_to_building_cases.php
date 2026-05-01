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
            $table->text('voiceover_full')->nullable()->after('character_nickname');
            $table->string('voice_id_preferred', 64)->nullable()->after('voiceover_full');
            $table->text('compliance_watermark')->nullable()->after('voice_id_preferred');
            $table->json('compliance_footer')->nullable()->after('compliance_watermark');
            $table->json('bgm_keywords')->nullable()->after('compliance_footer');
            $table->boolean('review_passed')->default(false)->after('bgm_keywords');
            $table->decimal('review_v2_score', 3, 1)->nullable()->after('review_passed');
            $table->json('review_meta')->nullable()->after('review_v2_score');
        });
    }

    public function down(): void
    {
        Schema::table('building_cases', function (Blueprint $table) {
            $table->dropColumn([
                'voiceover_full', 'voice_id_preferred',
                'compliance_watermark', 'compliance_footer',
                'bgm_keywords', 'review_passed', 'review_v2_score', 'review_meta',
            ]);
        });
    }
};
