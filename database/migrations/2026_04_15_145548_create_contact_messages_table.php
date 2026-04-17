<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->index();
            $table->foreignId('project_id')->nullable()->index();
            $table->string('inquiry_type')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('line_id')->nullable();
            $table->string('contact_time')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');
            $table->string('source_page')->nullable();
            $table->foreignId('assigned_to')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
