<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\SendNotificationRequest;
use App\Models\User;
use App\Notifications\DatabaseCustomNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    /**
     * Send or broadcast in-app notification to users.
     */
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification.send') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification.send permission.',
            ], 403);
        }

        $validated = $request->validated();
        $payload = [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'action_url' => $validated['action_url'] ?? null,
            'type' => $validated['type'] ?? 'admin_broadcast',
            'metadata' => $validated['metadata'] ?? [],
        ];

        $recipientType = $validated['recipient_type'];
        $recipientCount = 0;

        if ($recipientType === 'single') {
            $user = User::find($validated['user_id']);
            if ($user) {
                $user->notify(new DatabaseCustomNotification($payload));
                $recipientCount = 1;
            }
        } elseif ($recipientType === 'role') {
            $users = User::role($validated['role'])->where('status', 'active')->get();
            Notification::send($users, new DatabaseCustomNotification($payload));
            $recipientCount = $users->count();
        } elseif ($recipientType === 'all') {
            $users = User::where('status', 'active')->get();
            Notification::send($users, new DatabaseCustomNotification($payload));
            $recipientCount = $users->count();
        }

        return response()->json([
            'status' => true,
            'message' => "Notification sent successfully to {$recipientCount} user(s).",
            'data' => [
                'recipient_count' => $recipientCount,
            ],
        ], 200);
    }
}
