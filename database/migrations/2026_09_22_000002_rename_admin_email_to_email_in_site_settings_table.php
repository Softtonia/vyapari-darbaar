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
            if (Schema::hasColumn('site_settings', 'admin_email')) {
                $table->renameColumn('admin_email', 'email');
            } elseif (! Schema::hasColumn('site_settings', 'email')) {
                $table->string('email', 150)->nullable()->after('site_description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (Schema::hasColumn('site_settings', 'email')) {
                $table->renameColumn('email', 'admin_email');
            }
        });
    }
};
