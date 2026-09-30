<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('website_name');
            $table->string('link');
            $table->text('description')->nullable();
            $table->enum('job_type', ['daily', 'weekly', 'monthly', 'quarterly'])->default('daily');
            $table->time('schedule_time')->nullable(); // e.g. 07:00:00 (daily, weekly, etc.)
            $table->tinyInteger('schedule_day_of_week')->nullable();
            $table->tinyInteger('schedule_day_of_month')->nullable();
            $table->tinyInteger('schedule_month_of_quarter')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};