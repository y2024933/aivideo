<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 建立 project_statuses 表
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. 在 projects 表新增 nullable FK 欄位
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_status_id')->nullable()->after('status');
        });

        // 3. 搬資料：從 projects 讀取 distinct (site_id, status) 建立 project_statuses 記錄
        $distinctStatuses = DB::table('projects')
            ->select('site_id', 'status')
            ->distinct()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->get();

        foreach ($distinctStatuses as $row) {
            DB::table('project_statuses')->insert([
                'site_id' => $row->site_id,
                'name' => $row->status,
                'slug' => Str::slug($row->status),
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 回填 project_status_id
        $allStatuses = DB::table('project_statuses')->get();
        foreach ($allStatuses as $ps) {
            DB::table('projects')
                ->where('site_id', $ps->site_id)
                ->where('status', $ps->name)
                ->update(['project_status_id' => $ps->id]);
        }

        // 4. 清理
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->foreign('project_status_id')->references('id')->on('project_statuses')->nullOnDelete();
        });

        if (Schema::hasColumn('sites', 'project_statuses')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn('project_statuses');
            });
        }
    }

    public function down(): void
    {
        // 還原 sites.project_statuses JSON 欄位
        if (! Schema::hasColumn('sites', 'project_statuses')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->json('project_statuses')->nullable()->after('tracking');
            });
        }

        // 還原 projects.status 欄位
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_status_id']);
            $table->string('status')->nullable()->after('slug');
        });

        // 回填 status 文字
        $allStatuses = DB::table('project_statuses')->get();
        foreach ($allStatuses as $ps) {
            DB::table('projects')
                ->where('project_status_id', $ps->id)
                ->update(['status' => $ps->name]);
        }

        // 回填 sites.project_statuses JSON
        $sites = DB::table('project_statuses')
            ->select('site_id', DB::raw('JSON_ARRAYAGG(name) as names'))
            ->groupBy('site_id')
            ->get();

        foreach ($sites as $row) {
            DB::table('sites')
                ->where('id', $row->site_id)
                ->update(['project_statuses' => $row->names]);
        }

        // 移除 project_status_id 欄位與 project_statuses 表
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('project_status_id');
        });

        Schema::dropIfExists('project_statuses');
    }
};
