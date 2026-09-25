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
        Schema::create('news_import_runs', function (Blueprint $table) {
            $table->id();

            // Resolved source at time of run (nullable — stored even if resolution fails)
            $table->foreignId('news_source_id')
                ->nullable()
                ->constrained('news_sources')
                ->restrictOnDelete();

            // Resolved category at time of run (nullable)
            $table->foreignId('news_category_id')
                ->nullable()
                ->constrained('news_categories')
                ->restrictOnDelete();

            // The feed URL that was fetched
            $table->string('feed_url', 2048);

            // Import lifecycle status
            $table->string('status', 20)->default('pending');

            // Item counters
            $table->unsignedInteger('items_received')->default(0);
            $table->unsignedInteger('items_imported')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);

            // Lifecycle timestamps
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            // Bounded error collection (max ~50 messages enforced at service layer)
            $table->json('error_summary')->nullable();

            // Admin who triggered the run (nullable — may be NULL for scheduled runs)
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->foreign('triggered_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->timestamps();

            // Composite indexes for efficient admin list queries
            $table->index(['status', 'created_at', 'id'], 'nir_status_created_id_idx');
            $table->index(['news_source_id', 'status', 'created_at', 'id'], 'nir_src_status_created_id_idx');
            $table->index(['news_category_id', 'created_at', 'id'], 'nir_cat_created_id_idx');
            $table->index(['triggered_by', 'created_at', 'id'], 'nir_triggered_created_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_import_runs');
    }
};
