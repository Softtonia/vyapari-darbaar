<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class CampaignEmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'CAMPAIGN_WELCOME_USER',
                'name' => 'Welcome User Template',
                'subject' => 'Welcome to {{CompanyName}}!',
                'type' => 'html',
                'is_active' => true,
                'body' => <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Welcome</title></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Welcome to our platform, {{name}}!</h2>
    <p>We are thrilled to have you on board.</p>
    <p>Get started by exploring your dashboard and updating your profile.</p>
    <p>Thanks,<br>The {{CompanyName}} Team</p>
</body>
</html>
HTML
            ],
            [
                'key' => 'CAMPAIGN_FORGET_PASSWORD',
                'name' => 'Forgot Password Template',
                'subject' => 'Password Reset Request',
                'type' => 'html',
                'is_active' => true,
                'body' => <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Hello {{name}},</h2>
    <p>We received a request to reset your password. If you initiated this, please follow the instructions on the app.</p>
    <p>If you did not request a password reset, you can safely ignore this email.</p>
    <p>Regards,<br>Support Team</p>
</body>
</html>
HTML
            ],
            [
                'key' => 'CAMPAIGN_CHANGE_PASSWORD',
                'name' => 'Password Changed Alert',
                'subject' => 'Security Alert: Password Changed',
                'type' => 'html',
                'is_active' => true,
                'body' => <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Password Changed</title></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Security Alert, {{name}}</h2>
    <p>Your password was recently changed successfully.</p>
    <p>If you did not make this change, please contact our support team immediately to secure your account.</p>
    <p>Thanks,<br>Security Team</p>
</body>
</html>
HTML
            ],
            [
                'key' => 'CAMPAIGN_UPDATE_PROFILE',
                'name' => 'Profile Updated Template',
                'subject' => 'Your Profile has been Updated',
                'type' => 'html',
                'is_active' => true,
                'body' => <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Profile Updated</title></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Hello {{name}},</h2>
    <p>We wanted to let you know that your profile details were recently updated.</p>
    <p>If everything looks good, no further action is needed.</p>
    <p>Best,<br>The {{CompanyName}} Team</p>
</body>
</html>
HTML
            ]
        ];

        foreach ($templates as $data) {
            EmailTemplate::updateOrCreate(
                ['key' => $data['key']],
                $data
            );
        }
    }
}
