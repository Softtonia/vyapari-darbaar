<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('name');
            $table->index('status');
        });

        Schema::create('company_business_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('business_category_id')->constrained('business_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->index('company_id');
            $table->index('business_category_id');
        });

        if (Schema::hasColumn('companies', 'business_category')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('business_category');
            });
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'business_category')) {
                $table->string('business_category', 100)->nullable();
            }
        });

        Schema::dropIfExists('company_business_categories');
        Schema::dropIfExists('business_categories');
    }
};
