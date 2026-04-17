<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 先加一個暫存欄位存名字
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('assigned_name', 100)->nullable()->after('assigned_to');
        });

        DB::statement("UPDATE contact_messages cm LEFT JOIN users u ON cm.assigned_to = u.id SET cm.assigned_name = u.name WHERE cm.assigned_to IS NOT NULL");

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('assigned_to');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->renameColumn('assigned_name', 'assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_to')->nullable()->change();
        });
    }
};
