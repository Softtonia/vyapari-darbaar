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
        Schema::create('commodities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commodity_category_id')->constrained('commodity_categories')->restrictOnDelete();
            $table->string('name_en', 150);
            $table->string('name_hi', 150)->nullable();
            $table->string('slug', 180)->unique();
            $table->text('description_en')->nullable();
            $table->text('description_hi')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Composite index optimized for category, status, and sorting filters
            $table->index(['commodity_category_id', 'status', 'sort_order', 'id'], 'idx_commodities_cat_status_sort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commodities');
    }
};
