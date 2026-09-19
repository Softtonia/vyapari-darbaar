<?php

namespace App\Actions\Admin\User;

use App\Enums\NotificationType;
use App\Jobs\SendUserNotificationJob;
use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Support\Facades\DB;

class UpdateUserStatusAction
{
    /**
     * Update user status, store suspension reason if applicable, and revoke all tokens if inactive or suspended.
     */
    public function execute(User $user, string $status, ?string $reason = null): User
    {
        return DB::transaction(function () use ($user, $status, $reason) {
            $updateData = [
                'status' => $status,
            ];

            if ($status === 'suspended') {
                $updateData['suspension_reason'] = $reason ?? $user->suspension_reason;
            } elseif ($status === 'active') {
                $updateData['suspension_reason'] = null;
            } elseif ($reason !== null) {
                $updateData['suspension_reason'] = $reason;
            }

            $user->update($updateData);

            // If user is deactivated or suspended, revoke all existing tokens immediately
            if (in_array($status, ['inactive', 'suspended'], true)) {
                $user->tokens()->delete();
            }

            // Log activity
            $actionDesc = match ($status) {
                'suspended' => 'User account suspended'.($reason ? " (Reason: {$reason})" : ''),
                'inactive' => 'User account deactivated'.($reason ? " (Reason: {$reason})" : ''),
                'active' => 'User account activated',
                default => "User status updated to '{$status}'",
            };

            UserActivityService::log(
                $user,
                'status_updated',
                $actionDesc,
                [
                    'status' => $status,
                    'reason' => $reason,
                ]
            );

            // Automated Account Status Notification
            $notificationMessage = match ($status) {
                'suspended' => $reason
                    ? "Hello {{user_first_name}}, your account has been suspended. Reason: {$reason}"
                    : "Hello {{user_first_name}}, your account status has been updated to 'suspended'.",
                'active' => 'Hello {{user_first_name}}, your account has been activated.',
                default => "Hello {{user_first_name}}, your account status has been updated to '{$status}'.",
            };

            SendUserNotificationJob::dispatch(
                $user->id,
                'Account Status Updated',
                $notificationMessage,
                NotificationType::PUSH_AND_IN_APP
            );

            return $user->fresh();
        });
    }
}
