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
        Schema::create('market_ingestion_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')
                ->constrained('exchanges')
                ->restrictOnDelete();

            $table->string('source_type', 32);
            $table->date('trade_date')->nullable();

            $table->string('source_file_name', 255)->nullable();
            $table->char('source_checksum', 64)->nullable();
            $table->string('storage_path', 512)->nullable();

            $table->string('status', 32)->default('pending');

            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_inserted')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->json('error_summary')->nullable();

            $table->timestamps();

            // Compound and lookup indexes
            $table->index(['exchange_id', 'source_type', 'status', 'created_at'], 'idx_ingestion_lookup');
            $table->index(['source_type', 'trade_date'], 'idx_ingestion_type_date');
            $table->index('source_checksum', 'idx_ingestion_checksum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_ingestion_runs');
    }
};
