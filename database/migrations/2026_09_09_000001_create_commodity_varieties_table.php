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
        Schema::create('commodity_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_id')->constrained('commodities')->restrictOnDelete();
            $table->foreignId('commodity_subcategory_id')->nullable()->constrained('commodity_subcategories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique slug per commodity (excludes nullable commodity_subcategory_id)
            $table->unique(['commodity_id', 'slug'], 'uk_comm_varieties_commodity_slug');

            // Composite indexes for fast commodity and subcategory scoped queries
            $table->index(['commodity_id', 'status', 'sort_order', 'id'], 'idx_comm_varieties_commodity_status_sort_id');
            $table->index(['commodity_subcategory_id', 'status', 'sort_order', 'id'], 'idx_comm_varieties_subcat_status_sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commodity_varieties');
    }
};
