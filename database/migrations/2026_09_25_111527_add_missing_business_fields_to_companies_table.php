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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('pan_number')->nullable()->after('gstin');
            $table->string('year_of_establishment')->nullable()->after('pan_number');
            $table->string('business_category')->nullable()->after('year_of_establishment');
            $table->string('no_of_employees')->nullable()->after('business_category');
            $table->string('website')->nullable()->after('no_of_employees');
            
            // Address details
            $table->string('address_line_2')->nullable()->after('address'); // Assuming 'address' is used as line 1
            $table->string('pin_code')->nullable()->after('city');

            // Documents (Paths)
            $table->string('aadhaar_card_path')->nullable();
            $table->string('pan_card_path')->nullable();
            $table->string('passport_photo_path')->nullable();
            $table->string('gst_certificate_path')->nullable();
            $table->string('business_registration_path')->nullable();

            // Bank Details
            $table->string('bank_account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc_code')->nullable();
            $table->string('bank_branch_name')->nullable();

            $table->text('business_description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'pan_number', 'year_of_establishment', 'business_category', 'no_of_employees', 'website',
                'address_line_2', 'pin_code',
                'aadhaar_card_path', 'pan_card_path', 'passport_photo_path', 'gst_certificate_path', 'business_registration_path',
                'bank_account_holder_name', 'bank_name', 'bank_account_number', 'bank_ifsc_code', 'bank_branch_name',
                'business_description'
            ]);
        });
    }
};
