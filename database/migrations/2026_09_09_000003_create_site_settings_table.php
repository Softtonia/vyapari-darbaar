<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 150);
            $table->string('site_title', 255)->nullable();
            $table->text('site_description')->nullable();
            $table->string('web_logo', 500)->nullable();
            $table->string('mobile_logo', 500)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('favicon', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone', 100)->default('UTC');
            $table->string('default_language', 20)->default('en');
            $table->string('currency', 10)->default('INR');
            $table->string('phone_number', 25)->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
