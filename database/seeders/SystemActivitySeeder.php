<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SystemActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure required roles and representative users exist
        $superAdmin = User::query()->where('email', 'admin@vyaparidarbaar.com')->first();
        if (! $superAdmin) {
            $superAdmin = User::query()->first();
        }

        $contentManagerRole = Role::firstOrCreate(['name' => 'content_manager', 'guard_name' => 'web']);
        $traderManagerRole = Role::firstOrCreate(['name' => 'trader_manager', 'guard_name' => 'web']);
        $marketingManagerRole = Role::firstOrCreate(['name' => 'marketing_manager', 'guard_name' => 'web']);

        $rohit = User::firstOrCreate(
            ['email' => 'rohit.kumar@vyaparidarbaar.com'],
            [
                'first_name' => 'Rohit',
                'last_name' => 'Kumar',
                'full_name' => 'Rohit Kumar',
                'username' => 'rohit_kumar',
                'password' => Hash::make('Password#2026'),
                'status' => 'active',
            ]
        );
        $rohit->syncRoles([$contentManagerRole]);

        $neha = User::firstOrCreate(
            ['email' => 'neha.singh@vyaparidarbaar.com'],
            [
                'first_name' => 'Neha',
                'last_name' => 'Singh',
                'full_name' => 'Neha Singh',
                'username' => 'neha_singh',
                'password' => Hash::make('Password#2026'),
                'status' => 'active',
            ]
        );
        $neha->syncRoles([$traderManagerRole]);

        $amit = User::firstOrCreate(
            ['email' => 'amit.verma@vyaparidarbaar.com'],
            [
                'first_name' => 'Amit',
                'last_name' => 'Verma',
                'full_name' => 'Amit Verma',
                'username' => 'amit_verma',
                'password' => Hash::make('Password#2026'),
                'status' => 'active',
            ]
        );
        $amit->syncRoles([$marketingManagerRole]);

        $pooja = User::firstOrCreate(
            ['email' => 'pooja.mehta@vyaparidarbaar.com'],
            [
                'first_name' => 'Pooja',
                'last_name' => 'Mehta',
                'full_name' => 'Pooja Mehta',
                'username' => 'pooja_mehta',
                'password' => Hash::make('Password#2026'),
                'status' => 'active',
            ]
        );
        $pooja->syncRoles([$traderManagerRole]);

        // 2. Clear existing sample activities if seeding fresh or insert if missing
        $logs = [
            [
                'user_id' => $superAdmin?->id,
                'module' => 'Website',
                'action' => 'Updated',
                'event' => 'website.updated',
                'description' => 'Updated homepage banner',
                'ip_address' => '103.21.45.67',
                'status' => 'Success',
                'properties' => ['banner' => 'mandi_fest_2026.webp', 'section' => 'hero'],
                'created_at' => Carbon::parse('2026-09-17 10:22:00'),
            ],
            [
                'user_id' => $rohit->id,
                'module' => 'News',
                'action' => 'Created',
                'event' => 'news.published',
                'description' => 'Published new article: Makhana Market Growth',
                'ip_address' => '49.36.12.89',
                'status' => 'Success',
                'properties' => ['article_id' => 101, 'category' => 'Mandi Trends'],
                'created_at' => Carbon::parse('2026-09-17 09:48:00'),
            ],
            [
                'user_id' => $neha->id,
                'module' => 'Users',
                'action' => 'Updated',
                'event' => 'trader.verified',
                'description' => 'Updated trader verification status',
                'ip_address' => '103.21.45.67',
                'status' => 'Success',
                'properties' => ['trader_id' => 450, 'status' => 'verified'],
                'created_at' => Carbon::parse('2026-09-17 08:31:00'),
            ],
            [
                'user_id' => null,
                'module' => 'Auth',
                'action' => 'Login',
                'event' => 'auth.login_system',
                'description' => 'User login successful',
                'ip_address' => null,
                'status' => 'Success',
                'properties' => ['method' => 'API Token'],
                'created_at' => Carbon::parse('2026-09-16 19:15:00'),
            ],
            [
                'user_id' => $amit->id,
                'module' => 'Ads',
                'action' => 'Created',
                'event' => 'ads.campaign_created',
                'description' => 'Created new advertisement campaign',
                'ip_address' => '182.74.33.12',
                'status' => 'Success',
                'properties' => ['campaign' => 'Navratri Special Traders Discount'],
                'created_at' => Carbon::parse('2026-09-16 18:42:00'),
            ],
            [
                'user_id' => $pooja->id,
                'module' => 'Payments',
                'action' => 'Updated',
                'event' => 'payment.reconciled',
                'description' => 'Updated payment status for invoice #8921',
                'ip_address' => '49.36.12.89',
                'status' => 'Success',
                'properties' => ['invoice' => '#8921', 'amount' => 15000, 'currency' => 'INR'],
                'created_at' => Carbon::parse('2026-09-16 17:28:00'),
            ],
            [
                'user_id' => $superAdmin?->id,
                'module' => 'Settings',
                'action' => 'Updated',
                'event' => 'site_settings.updated',
                'description' => 'Updated site contact email and timezone settings',
                'ip_address' => '103.21.45.67',
                'status' => 'Success',
                'properties' => ['email' => 'admin@vyaparidarbaar.com', 'timezone' => 'Asia/Kolkata'],
                'created_at' => Carbon::parse('2026-09-16 15:10:00'),
            ],
            [
                'user_id' => $rohit->id,
                'module' => 'News',
                'action' => 'Updated',
                'event' => 'news.featured_toggled',
                'description' => 'Featured article: Wheat MSP Hike Approved',
                'ip_address' => '49.36.12.89',
                'status' => 'Success',
                'properties' => ['article_id' => 102, 'is_featured' => true],
                'created_at' => Carbon::parse('2026-09-16 14:05:00'),
            ],
            [
                'user_id' => $superAdmin?->id,
                'module' => 'Mandis',
                'action' => 'Created',
                'event' => 'mandi.created',
                'description' => 'Added new APMC mandi: Nimbahera Mandi (Rajasthan)',
                'ip_address' => '103.21.45.67',
                'status' => 'Success',
                'properties' => ['mandi' => 'Nimbahera', 'district' => 'Chittorgarh'],
                'created_at' => Carbon::parse('2026-09-16 11:30:00'),
            ],
            [
                'user_id' => $neha->id,
                'module' => 'Commodities',
                'action' => 'Status Changed',
                'event' => 'commodity.status_updated',
                'description' => 'Activated Mustard Seed contract rates',
                'ip_address' => '103.21.45.67',
                'status' => 'Success',
                'properties' => ['commodity' => 'Mustard Seed', 'status' => 'active'],
                'created_at' => Carbon::parse('2026-09-15 16:45:00'),
            ],
        ];

        foreach ($logs as $logData) {
            UserActivity::updateOrCreate(
                [
                    'module' => $logData['module'],
                    'action' => $logData['action'],
                    'description' => $logData['description'],
                    'created_at' => $logData['created_at'],
                ],
                $logData
            );
        }
    }
}
