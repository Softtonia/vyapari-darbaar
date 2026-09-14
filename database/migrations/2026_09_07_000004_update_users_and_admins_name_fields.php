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
        // Update users table
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'first_name')) {
                    $table->string('first_name', 100)->nullable()->after('id');
                }
                if (!Schema::hasColumn('users', 'last_name')) {
                    $table->string('last_name', 100)->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('users', 'full_name')) {
                    if (Schema::hasColumn('users', 'name')) {
                        $table->renameColumn('name', 'full_name');
                    } else {
                        $table->string('full_name', 150)->after('last_name');
                    }
                }
                if (!Schema::hasColumn('users', 'phone_number')) {
                    $table->string('phone_number', 25)->nullable()->after('full_name')->index();
                }
            });

            // Backfill full_name, first_name, last_name if any are blank
            $users = DB::table('users')->select('id', 'first_name', 'last_name', 'full_name')->get();
            foreach ($users as $user) {
                $fullName = trim((string) ($user->full_name ?? ''));
                $firstName = trim((string) ($user->first_name ?? ''));
                $lastName = trim((string) ($user->last_name ?? ''));

                if ($fullName === '' && ($firstName !== '' || $lastName !== '')) {
                    $fullName = trim("{$firstName} {$lastName}");
                } elseif ($fullName !== '' && ($firstName === '' && $lastName === '')) {
                    $parts = preg_split('/\s+/', $fullName, 2);
                    $firstName = !empty($parts[0]) ? $parts[0] : 'User';
                    $lastName = $parts[1] ?? '';
                }

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName ?: null,
                    'full_name' => $fullName ?: 'User',
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'full_name') && !Schema::hasColumn('users', 'name')) {
                    $table->renameColumn('full_name', 'name');
                }
            });
        }
    }
};
