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
        Schema::create('exchange_commodity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges')->cascadeOnDelete();
            $table->foreignId('commodity_id')->constrained('commodities')->cascadeOnDelete();
            $table->string('external_symbol', 100);
            $table->string('external_code', 100)->nullable();
            $table->string('external_name', 180)->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exchange_id', 'external_symbol'], 'uq_ecm_exchange_ext_symbol');
            $table->unique(['id', 'exchange_id'], 'uq_ecm_id_exchange');
            $table->index(['commodity_id', 'status'], 'idx_ecm_commodity_status');
            $table->index(['exchange_id', 'status'], 'idx_ecm_exchange_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_commodity_mappings');
    }
};
