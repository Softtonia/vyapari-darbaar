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
        $tableNames = config('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $columnNames = config('permission.column_names', [
            'role_pivot_key' => null,
            'permission_pivot_key' => null,
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        // 1. Drop old custom pivot tables if they exist
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('admin_role');

        // 2. Adapt existing roles table for Spatie
        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) {
                if (!Schema::hasColumn('roles', 'guard_name')) {
                    $table->string('guard_name', 50)->default('admin')->after('name');
                }
            });

            // Ensure unique constraint on name and guard_name
            if (!Schema::hasIndex($tableNames['roles'], 'roles_name_guard_name_unique')) {
                Schema::table($tableNames['roles'], function (Blueprint $table) {
                    $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
                });
            }
        } else {
            Schema::create($tableNames['roles'], function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('guard_name', 50)->default('admin');
                $table->string('slug', 100)->nullable();
                $table->boolean('status')->default(true)->index();
                $table->boolean('is_system')->default(false)->index();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
                $table->index(['created_at', 'id']);
            });
        }

        // 3. Create permissions table
        if (!Schema::hasTable($tableNames['permissions'])) {
            Schema::create($tableNames['permissions'], function (Blueprint $table) {
                $table->id();
                $table->string('name', 125);
                $table->string('guard_name', 50);
                $table->timestamps();

                $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
            });
        }

        // 4. Create model_has_permissions table
        if (!Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission) {
                $table->unsignedBigInteger($pivotPermission);
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        // 5. Create model_has_roles table
        if (!Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole) {
                $table->unsignedBigInteger($pivotRole);
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }

        // 6. Create role_has_permissions table
        if (!Schema::hasTable($tableNames['role_has_permissions'])) {
            Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
            });
        }

        // 7. Clear Spatie permission cache
        try {
            app('cache')
                ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
                ->forget(config('permission.cache.key'));
        } catch (\Throwable $e) {
            // Ignore cache errors during migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['permissions']);

        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($tableNames) {
                if (Schema::hasIndex($tableNames['roles'], 'roles_name_guard_name_unique')) {
                    $table->dropUnique('roles_name_guard_name_unique');
                }
                if (Schema::hasColumn($tableNames['roles'], 'guard_name')) {
                    $table->dropColumn('guard_name');
                }
            });
        }
    }
};
