<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add normalized FK columns (country_id, state_id, city_id),
     * extra business fields, and drop old plain-string location columns.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Normalized location FKs (nullable so existing rows don't break)
            $table->foreignId('country_id')->nullable()->after('gstin')->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->after('country_id')->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities')->nullOnDelete();
            $table->string('pin_code', 20)->nullable()->after('city_id');

            // Extra business / profile fields
            $table->string('company_email', 255)->nullable()->after('contact_person');
            $table->string('company_phone', 30)->nullable()->after('company_email');
            $table->string('website', 255)->nullable()->after('company_phone');
            $table->string('pan_number', 20)->nullable()->after('gstin');
            $table->string('year_of_establishment', 10)->nullable()->after('pan_number');
            $table->string('business_category', 100)->nullable()->after('year_of_establishment');
            $table->string('no_of_employees', 50)->nullable()->after('business_category');
            $table->text('business_description')->nullable()->after('no_of_employees');
            $table->string('buy_sell_preference', 20)->nullable()->after('trade_preference');

            // Drop old plain-string location columns
            $table->dropIndex(['city']);
            $table->dropIndex(['state']);
            $table->dropColumn(['country', 'state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Restore old string columns
            $table->string('country', 100)->nullable()->default('India');
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();

            // Drop new columns
            $table->dropForeign(['country_id']);
            $table->dropForeign(['state_id']);
            $table->dropForeign(['city_id']);
            $table->dropColumn([
                'country_id', 'state_id', 'city_id', 'pin_code',
                'company_email', 'company_phone', 'website', 'pan_number',
                'year_of_establishment', 'business_category', 'no_of_employees',
                'business_description', 'buy_sell_preference',
            ]);
        });
    }
};

