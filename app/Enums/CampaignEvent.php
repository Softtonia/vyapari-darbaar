<?php

namespace App\Enums;

enum CampaignEvent: string
{
    case REGISTER = 'register';
    case FORGET_PASSWORD = 'forget_password';
    case WELCOME_USER = 'welcome_user';
    case ADD_USER = 'add_user';
    case UPDATE_USER = 'update_user';
    case CHANGE_PASSWORD = 'change_password';
    case TICKET_RAISED = 'ticket_raised';
    case TICKET_RESOLVED = 'ticket_resolved';
    case UPDATE_PROFILE = 'update_profile';

    public function label(): string
    {
        return match ($this) {
            self::REGISTER => 'Register',
            self::FORGET_PASSWORD => 'Forget Password',
            self::WELCOME_USER => 'Welcome User',
            self::ADD_USER => 'Add User',
            self::UPDATE_USER => 'Update User',
            self::CHANGE_PASSWORD => 'Change Password',
            self::TICKET_RAISED => 'Ticket Raised',
            self::TICKET_RESOLVED => 'Ticket Resolved',
            self::UPDATE_PROFILE => 'Update Profile',
        };
    }
    
    /**
     * Get all events as an array of value/label objects for API response.
     */
    public static function forApi(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
