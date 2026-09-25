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
        Schema::create('company_kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('document_type', 100); // e.g. aadhaar_card, pan_card, gst_certificate, business_registration, passport_photo
            $table->string('file_path', 500);
            $table->string('status', 50)->default('pending'); // pending, verified, rejected
            $table->string('upload_batch_id', 100)->nullable(); // For batch upload progress tracking
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_kyc_documents');
    }
};
