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
        Schema::table('commodity_subcategories', function (Blueprint $table) {
            $table->dropIndex('idx_comm_subcats_status_sort_id');
            $table->index(['commodity_id', 'status', 'sort_order', 'id'], 'idx_comm_subcats_commodity_status_sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commodity_subcategories', function (Blueprint $table) {
            $table->dropIndex('idx_comm_subcats_commodity_status_sort_id');
            $table->index(['commodity_id', 'status', 'sort_order', 'id'], 'idx_comm_subcats_status_sort_id');
        });
    }
};
