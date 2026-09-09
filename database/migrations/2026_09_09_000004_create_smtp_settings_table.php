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
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mailer', 50)->default('smtp');
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username', 255);
            $table->text('password');
            $table->string('from_email', 255);
            $table->string('from_name', 150)->nullable();
            $table->string('encryption', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }
};
