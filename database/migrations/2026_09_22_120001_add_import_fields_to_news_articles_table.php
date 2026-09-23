<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds two nullable columns for auto-import tracking:
     * - external_id: stable identifier from the originating feed (e.g. PIB PRID)
     * - imported_at: timestamp when the article was auto-imported
     *
     * Manual articles continue to work with both fields NULL.
     * UNIQUE(news_source_id, external_id) enforces primary duplicate guard.
     */
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('external_id', 255)->nullable()->after('source_url');
            $table->dateTime('imported_at')->nullable()->after('external_id');

            // Primary duplicate guard: one external_id per source
            $table->unique(['news_source_id', 'external_id'], 'na_source_external_id_udx');

            // Index for fast lookup on import runs
            $table->index(['news_source_id', 'external_id'], 'na_src_ext_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropUnique('na_source_external_id_udx');
            $table->dropIndex('na_src_ext_id_idx');
            $table->dropColumn(['external_id', 'imported_at']);
        });
    }
};
