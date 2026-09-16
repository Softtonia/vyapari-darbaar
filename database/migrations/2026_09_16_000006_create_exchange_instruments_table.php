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
        Schema::create('exchange_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges')->restrictOnDelete();
            $table->foreignId('exchange_commodity_mapping_id')->constrained('exchange_commodity_mappings')->restrictOnDelete();
            $table->string('external_instrument_id', 100)->nullable();
            $table->string('symbol', 150);
            $table->string('instrument_name', 255)->nullable();
            $table->string('instrument_type', 50);
            $table->date('original_expiry_date')->nullable();
            $table->date('actual_expiry_date');
            $table->decimal('strike_price', 20, 8)->nullable();
            $table->string('option_type', 20)->nullable();
            $table->decimal('lot_size', 20, 6)->nullable();
            $table->decimal('tick_size', 20, 8)->nullable();
            $table->string('quote_unit', 100)->nullable();
            $table->string('contract_unit', 100)->nullable();
            $table->string('lifecycle_status', 20)->default('active');
            $table->boolean('is_enabled')->default(true);
            $table->dateTime('listed_at')->nullable();
            $table->dateTime('delisted_at')->nullable();
            $table->timestamps();

            $table->foreign(['exchange_commodity_mapping_id', 'exchange_id'], 'fk_instr_mapping_exchange')
                ->references(['id', 'exchange_id'])
                ->on('exchange_commodity_mappings')
                ->restrictOnDelete();

            $table->unique(['exchange_id', 'external_instrument_id'], 'uq_instr_exchange_ext_id');

            $table->index(['exchange_commodity_mapping_id', 'lifecycle_status', 'is_enabled', 'actual_expiry_date'], 'idx_instr_mapping_status_expiry');
            $table->index(['exchange_id', 'lifecycle_status', 'is_enabled', 'actual_expiry_date'], 'idx_instr_exchange_status_expiry');
            $table->index(['exchange_id', 'symbol', 'actual_expiry_date'], 'idx_instr_exchange_symbol_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_instruments');
    }
};
