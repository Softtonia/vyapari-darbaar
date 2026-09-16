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
        Schema::create('market_bhavcopies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_instrument_id')
                ->constrained('exchange_instruments')
                ->restrictOnDelete();
            $table->date('trade_date');

            // Price columns (DECIMAL 20,8) - Nullable, no default 0
            $table->decimal('open_price', 20, 8)->nullable();
            $table->decimal('high_price', 20, 8)->nullable();
            $table->decimal('low_price', 20, 8)->nullable();
            $table->decimal('close_price', 20, 8)->nullable();

            $table->decimal('last_price', 20, 8)->nullable();
            $table->decimal('previous_close_price', 20, 8)->nullable();
            $table->decimal('settlement_price', 20, 8)->nullable();

            // Quantity, turnover & trades
            $table->decimal('volume', 20, 6)->nullable();
            $table->decimal('traded_value', 24, 6)->nullable();
            $table->unsignedBigInteger('number_of_trades')->nullable();

            // Open Interest
            $table->decimal('open_interest', 20, 6)->nullable();
            $table->decimal('change_in_open_interest', 20, 6)->nullable();

            // Ingestion timestamps
            $table->dateTime('source_timestamp')->nullable();
            $table->dateTime('received_at');

            $table->timestamps();

            // Logical uniqueness constraint for idempotent EOD upserts
            $table->unique(['exchange_instrument_id', 'trade_date'], 'uk_bhavcopy_instrument_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_bhavcopies');
    }
};
