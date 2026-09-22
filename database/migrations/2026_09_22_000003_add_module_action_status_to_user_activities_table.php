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
        Schema::table('user_activities', function (Blueprint $table) {
            $table->string('module', 50)->nullable()->after('user_id')->index();
            $table->string('action', 50)->nullable()->after('module')->index();
            $table->string('status', 20)->default('Success')->after('description')->index();

            $table->index(['module', 'action', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex(['user_activities_module_action_created_at_index']);
            $table->dropIndex(['user_activities_status_created_at_index']);
            $table->dropColumn(['module', 'action', 'status']);
        });
    }
};
