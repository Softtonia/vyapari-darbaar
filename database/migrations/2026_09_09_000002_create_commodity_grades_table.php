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
        Schema::create('commodity_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_id')->constrained('commodities')->restrictOnDelete();
            $table->foreignId('commodity_subcategory_id')->nullable()->constrained('commodity_subcategories')->restrictOnDelete();
            $table->foreignId('commodity_variety_id')->nullable()->constrained('commodity_varieties')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique slug per commodity
            $table->unique(['commodity_id', 'slug'], 'uk_comm_grades_commodity_slug');

            // Composite indexes for fast commodity, subcategory, and variety scoped queries
            $table->index(['commodity_id', 'status', 'sort_order', 'id'], 'idx_comm_grades_commodity_status_sort_id');
            $table->index(['commodity_subcategory_id', 'status', 'sort_order', 'id'], 'idx_comm_grades_subcat_status_sort_id');
            $table->index(['commodity_variety_id', 'status', 'sort_order', 'id'], 'idx_comm_grades_variety_status_sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commodity_grades');
    }
};
