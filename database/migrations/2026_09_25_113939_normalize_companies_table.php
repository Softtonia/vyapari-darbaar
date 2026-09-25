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
            // Drop string columns
            $table->dropColumn(['country', 'state', 'city']);
            
            // Drop the other fields that we moved to separate tables (if they exist)
            // They were added in my previous migration, but if someone rolls back this one, they might need them
            if (Schema::hasColumn('companies', 'bank_name')) {
                $table->dropColumn([
                    'aadhaar_card_path', 'pan_card_path', 'passport_photo_path', 'gst_certificate_path', 'business_registration_path',
                    'bank_account_holder_name', 'bank_name', 'bank_account_number', 'bank_ifsc_code', 'bank_branch_name'
                ]);
            }
            
            // Add foreign keys
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            
            $table->dropForeign(['country_id']);
            $table->dropForeign(['state_id']);
            $table->dropForeign(['city_id']);
            
            $table->dropColumn(['country_id', 'state_id', 'city_id']);
        });
    }
};
