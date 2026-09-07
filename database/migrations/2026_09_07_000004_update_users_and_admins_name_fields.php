<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'first_name')) {
                    $table->string('first_name', 100)->nullable()->after('id');
                }
                if (!Schema::hasColumn('users', 'last_name')) {
                    $table->string('last_name', 100)->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('users', 'phone_number')) {
                    $table->string('phone_number', 20)->nullable()->after('last_name')->index();
                }
            });

            // Backfill users name into first_name and last_name
            if (Schema::hasColumn('users', 'name')) {
                $users = DB::table('users')->select('id', 'name')->get();
                foreach ($users as $user) {
                    $name = trim((string) ($user->name ?? ''));
                    $parts = preg_split('/\s+/', $name, 2);
                    $firstName = !empty($parts[0]) ? $parts[0] : 'User';
                    $lastName = $parts[1] ?? '';

                    DB::table('users')->where('id', $user->id)->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ]);
                }
            }

            // Make name column nullable if it exists
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'name')) {
                    $table->string('name', 150)->nullable()->change();
                }
            });
        }

        // 2. Update admins table
        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                if (!Schema::hasColumn('admins', 'first_name')) {
                    $table->string('first_name', 100)->nullable()->after('id');
                }
                if (!Schema::hasColumn('admins', 'last_name')) {
                    $table->string('last_name', 100)->nullable()->after('first_name');
                }
            });

            // Backfill admins name into first_name and last_name
            if (Schema::hasColumn('admins', 'name')) {
                $admins = DB::table('admins')->select('id', 'name')->get();
                foreach ($admins as $admin) {
                    $name = trim((string) ($admin->name ?? ''));
                    $parts = preg_split('/\s+/', $name, 2);
                    $firstName = !empty($parts[0]) ? $parts[0] : 'Admin';
                    $lastName = $parts[1] ?? '';

                    DB::table('admins')->where('id', $admin->id)->update([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ]);
                }
            }

            // Make name column nullable if it exists
            Schema::table('admins', function (Blueprint $table) {
                if (Schema::hasColumn('admins', 'name')) {
                    $table->string('name', 150)->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'last_name', 'phone_number']);
            });
        }

        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn(['first_name', 'last_name']);
            });
        }
    }
};
