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
        Schema::create('firebase_settings', function (Blueprint $table) {
            $table->id();
            $table->string('api_key', 255);
            $table->string('auth_domain', 255);
            $table->string('project_id', 255);
            $table->string('storage_bucket', 255)->nullable();
            $table->string('messaging_sender_id', 255);
            $table->string('app_id', 255);
            $table->text('vapid_key');
            $table->longText('service_account_json');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firebase_settings');
    }
};
