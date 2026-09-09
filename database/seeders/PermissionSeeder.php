<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminPermissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'commodity-category.view',
            'commodity-category.create',
            'commodity-category.update',
            'commodity-category.delete',
            'commodity-categories.view',
            'commodity-categories.create',
            'commodity-categories.update',
            'commodity-categories.delete',
            'commodity.view',
            'commodity.create',
            'commodity.update',
            'commodity.delete',
            'commodities.view',
            'commodities.create',
            'commodities.update',
            'commodities.delete',
            'commodity-subcategory.view',
            'commodity-subcategory.create',
            'commodity-subcategory.update',
            'commodity-subcategory.delete',
            'commodity-subcategories.view',
            'commodity-subcategories.create',
            'commodity-subcategories.update',
            'commodity-subcategories.delete',
            'commodity-variety.view',
            'commodity-variety.create',
            'commodity-variety.update',
            'commodity-variety.delete',
            'commodity-varieties.view',
            'commodity-varieties.create',
            'commodity-varieties.update',
            'commodity-varieties.delete',
        ];

        foreach ($adminPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'admin');
        }

        // Assign permissions to admin role
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions($adminPermissions);
        }
    }
}
