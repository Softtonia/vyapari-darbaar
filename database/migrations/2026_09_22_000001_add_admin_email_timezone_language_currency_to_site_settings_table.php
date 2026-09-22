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
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('admin_email', 150)->nullable()->after('site_description');
            $table->string('timezone', 100)->default('Asia/Kolkata')->after('admin_email');
            $table->string('default_language', 20)->default('en')->after('timezone');
            $table->string('currency', 20)->default('INR')->after('default_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'admin_email',
                'timezone',
                'default_language',
                'currency',
            ]);
        });
    }
};
