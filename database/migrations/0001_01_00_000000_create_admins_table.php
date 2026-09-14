<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Admins table removed in favor of unified users table.
     */
    public function up(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admins');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
        Schema::dropIfExists('admins');
    }
};
