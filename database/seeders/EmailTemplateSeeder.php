<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'USER_ACCOUNT_CREATED'],
            [
                'name' => 'New User Account Created',
                'subject' => 'Your Account Credentials',
                'body' => "Hello {{UserName}},\n\n"
                    . "Welcome!\n\n"
                    . "Your account has been successfully created by the administrator. Please find your login credentials below:\n\n"
                    . "Username: {{Username}}\n"
                    . "Password: {{TemporaryPassword}}\n\n"
                    . "You can use these credentials to log in to your account.\n\n"
                    . "For security reasons, we recommend changing your password after your first login.\n\n"
                    . "If you did not expect to receive this email, please contact the administrator.\n\n"
                    . "Regards,\n"
                    . "Admin Team\n"
                    . "{{CompanyName}}\n"
                    . "{{SupportEmail}}",
                'is_active' => true,
            ]
        );
    }
}
