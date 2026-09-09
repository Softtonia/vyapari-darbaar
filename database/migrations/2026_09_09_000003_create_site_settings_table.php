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
            $table->string('site_name_en', 150);
            $table->string('site_name_hi', 150)->nullable();
            $table->string('site_title_en', 255)->nullable();
            $table->string('site_title_hi', 255)->nullable();
            $table->text('site_description_en')->nullable();
            $table->text('site_description_hi')->nullable();
            $table->string('web_logo', 500)->nullable();
            $table->string('mobile_logo', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
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
