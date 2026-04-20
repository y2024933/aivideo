<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['has_news', 'has_projects', 'has_progress', 'has_contact_form']);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('has_news')->default(true);
            $table->boolean('has_projects')->default(true);
            $table->boolean('has_progress')->default(true);
            $table->boolean('has_contact_form')->default(true);
        });
    }
};
