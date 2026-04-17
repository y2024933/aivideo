<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 將現有 primary_domain 搬進 site_domains（若尚未存在）
        $sites = DB::table('sites')->whereNotNull('primary_domain')->get();

        foreach ($sites as $site) {
            DB::table('site_domains')->updateOrInsert(
                ['domain' => $site->primary_domain],
                ['site_id' => $site->id, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('primary_domain');
        });

        // 補上外鍵約束
        Schema::table('site_domains', function (Blueprint $table) {
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_domains', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->string('primary_domain')->nullable()->unique()->after('theme_key');
        });

        $primaryDomains = DB::table('site_domains')->where('is_primary', true)->get();

        foreach ($primaryDomains as $row) {
            DB::table('sites')->where('id', $row->site_id)->update(['primary_domain' => $row->domain]);
        }
    }
};
