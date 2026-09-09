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
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('contact_person', 150)->nullable();
                $table->string('business_type', 100)->nullable();
                $table->string('gstin', 20)->nullable();
                $table->string('country', 100)->nullable()->default('India');
                $table->string('state', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->text('address')->nullable();
                $table->json('commodities_handled')->nullable();
                $table->string('trade_preference', 20)->default('both'); // 'buy', 'sell', 'both'
                $table->string('verification_status', 20)->default('pending'); // 'pending', 'verified', 'rejected'
                $table->timestamps();

                $table->index('verification_status');
                $table->index('city');
                $table->index('state');
            });
        }

        if (! Schema::hasTable('user_has_companies')) {
            Schema::create('user_has_companies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('role', 50)->default('trader');
                $table->boolean('is_primary')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'company_id']);
                $table->index('user_id');
                $table->index('company_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_has_companies');
        Schema::dropIfExists('companies');
    }
};
