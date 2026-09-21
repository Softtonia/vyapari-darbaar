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
            'commodity-grade.view',
            'commodity-grade.create',
            'commodity-grade.update',
            'commodity-grade.delete',
            'commodity-grades.view',
            'commodity-grades.create',
            'commodity-grades.update',
            'commodity-grades.delete',
            'state.view',
            'state.create',
            'state.update',
            'state.delete',
            'states.view',
            'states.create',
            'states.update',
            'states.delete',
            'district.view',
            'district.create',
            'district.update',
            'district.delete',
            'districts.view',
            'districts.create',
            'districts.update',
            'districts.delete',
            'mandi.view',
            'mandi.create',
            'mandi.update',
            'mandi.delete',
            'mandis.view',
            'mandis.create',
            'mandis.update',
            'mandis.delete',
            'site-setting.view',
            'site-setting.update',
            'smtp-setting.view',
            'smtp-setting.update',
            'smtp-setting.test',
            'firebase-setting.view',
            'firebase-setting.update',
            'firebase-setting.test',
            'notification.send',
            'notification-dashboard.view',
            'notification-send.create',
            'notification-template.view',
            'notification-template.create',
            'notification-template.update',
            'notification-template.delete',
            'notification-batch.view',
            'notification-batch.cancel',
            'notification-batch.retry',
            'notification-log.view',
            'notification-in-app.view',
            'notification-device.view',
            'notification-device.update',
            'notification-device.delete',
            'notification-topic.view',
            'notification-topic.create',
            'notification-topic.update',
            'notification-topic.delete',
            'notification-topic.manage-users',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'exchanges.view',
            'exchanges.create',
            'exchanges.update',
            'exchanges.delete',
            'exchange-commodity-mappings.view',
            'exchange-commodity-mappings.create',
            'exchange-commodity-mappings.update',
            'exchange-commodity-mappings.delete',
            'exchange-instruments.view',
            'exchange-instruments.create',
            'exchange-instruments.update',
            'exchange-instruments.delete',
            'market-ingestion-runs.view',
            'news.view',
            'news.create',
            'news.update',
            'news.delete',
            'news.publish',
            'news-categories.view',
            'news-categories.create',
            'news-categories.update',
            'news-categories.delete',
            'news-sources.view',
            'news-sources.create',
            'news-sources.update',
            'news-sources.delete',
        ];

        foreach ($adminPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Assign permissions to super_admin role
        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions($adminPermissions);
        }

        // Assign permissions to admin role
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $adminRole->syncPermissions($adminPermissions);
        }
    }
}
