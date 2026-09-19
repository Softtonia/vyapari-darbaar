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
        $htmlBody = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7fa; color: #334155;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header -->
        <tr>
            <td style="background-color: #1e293b; padding: 28px 32px; text-align: center;">
                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">{{CompanyName}}</h1>
            </td>
        </tr>
        <!-- Content Body -->
        <tr>
            <td style="padding: 36px 32px;">
                <h2 style="margin: 0 0 16px 0; color: #0f172a; font-size: 20px; font-weight: 600;">Hello {{UserName}},</h2>
                <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                    Welcome! Your account has been successfully created by the administrator. Please find your login credentials below:
                </p>
                
                <!-- Credentials Box -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin: 24px 0;">
                    <tr>
                        <td style="padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #64748b; font-weight: 500; width: 120px;">Username:</td>
                        <td style="padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-size: 15px; color: #0f172a; font-weight: 600; font-family: monospace;">{{Username}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 14px 20px; font-size: 14px; color: #64748b; font-weight: 500;">Password:</td>
                        <td style="padding: 14px 20px; font-size: 15px; color: #0f172a; font-weight: 600; font-family: monospace;">{{TemporaryPassword}}</td>
                    </tr>
                </table>
                
                <p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                    <strong>Security Notice:</strong> For security reasons, we strongly recommend changing your password after your first login.
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                    If you did not expect to receive this email, please contact our support team at <a href="mailto:{{SupportEmail}}" style="color: #2563eb; text-decoration: underline;">{{SupportEmail}}</a>.
                </p>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px; text-align: center; font-size: 13px; color: #94a3b8;">
                <p style="margin: 0 0 4px 0;">Regards,</p>
                <p style="margin: 0; font-weight: 600; color: #64748b;">Admin Team &bull; {{CompanyName}}</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        EmailTemplate::updateOrCreate(
            ['key' => 'USER_ACCOUNT_CREATED'],
            [
                'name' => 'New User Account Created',
                'subject' => 'Your Account Credentials',
                'body' => $htmlBody,
                'type' => 'html',
                'is_active' => true,
            ]
        );

        $loginOtpHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login OTP</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f7fa; color: #334155;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <!-- Header -->
        <tr>
            <td style="background-color: #1e293b; padding: 28px 32px; text-align: center;">
                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">{{CompanyName}}</h1>
            </td>
        </tr>
        <!-- Content Body -->
        <tr>
            <td style="padding: 36px 32px;">
                <h2 style="margin: 0 0 16px 0; color: #0f172a; font-size: 20px; font-weight: 600;">Hello {{UserName}},</h2>
                <p style="margin: 0 0 20px 0; font-size: 15px; line-height: 1.6; color: #475569;">
                    You have requested to sign in to your <strong>{{CompanyName}}</strong> account using a One-Time Password (OTP).
                </p>
                
                <!-- OTP Box -->
                <div style="background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 24px; text-align: center; margin: 24px 0;">
                    <span style="font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; display: block; margin-bottom: 8px;">Your Login Verification Code</span>
                    <span style="font-size: 38px; font-weight: 800; color: #1e293b; letter-spacing: 6px; font-family: monospace;">{{Otp}}</span>
                </div>
                
                <p style="margin: 0 0 14px 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                    <strong>Security Notice:</strong> This code is valid for <strong>{{ExpiryMinutes}} minutes</strong>. Never share your OTP with anyone, including {{CompanyName}} staff.
                </p>
                <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                    If you did not request this login attempt, please ignore this email or contact support immediately at <a href="mailto:{{SupportEmail}}" style="color: #2563eb; text-decoration: underline;">{{SupportEmail}}</a>.
                </p>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px; text-align: center; font-size: 13px; color: #94a3b8;">
                <p style="margin: 0 0 4px 0;">Regards,</p>
                <p style="margin: 0; font-weight: 600; color: #64748b;">Security &bull; {{CompanyName}}</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        EmailTemplate::updateOrCreate(
            ['key' => 'USER_LOGIN_OTP'],
            [
                'name' => 'User Login OTP Verification',
                'subject' => 'Your Login OTP - {{CompanyName}}',
                'body' => $loginOtpHtml,
                'type' => 'html',
                'is_active' => true,
            ]
        );
    }
}
