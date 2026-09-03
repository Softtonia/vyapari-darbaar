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
        if (Schema::hasTable('users')) {
            if (!Schema::hasIndex('users', 'users_status_created_at_id_index')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index(['status', 'created_at', 'id'], 'users_status_created_at_id_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            if (Schema::hasIndex('users', 'users_status_created_at_id_index')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex('users_status_created_at_id_index');
                });
            }
        }
    }
};
