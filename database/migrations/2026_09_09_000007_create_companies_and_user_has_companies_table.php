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
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->text('address')->nullable();
                $table->string('address_line_2', 255)->nullable();
                $table->string('pin_code', 20)->nullable();
                $table->string('pan_number', 50)->nullable();
                $table->string('year_of_establishment', 10)->nullable();
                $table->string('no_of_employees', 50)->nullable();
                $table->string('website', 255)->nullable();
                $table->text('business_description')->nullable();
                $table->json('commodities_handled')->nullable();
                $table->string('trade_preference', 20)->default('both'); // 'buy', 'sell', 'both'
                $table->string('verification_status', 20)->default('pending'); // 'pending', 'verified', 'rejected'
                $table->timestamps();

                $table->index('verification_status');
                $table->index('country_id');
                $table->index('state_id');
                $table->index('city_id');
                $table->index('name');
                $table->index('business_type');
                $table->index('gstin');
                $table->index('pan_number');
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
