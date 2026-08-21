<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationRecipients
{
    /**
     * Roles that receive system alert notifications (order sync failures,
     * low stock, returns, shipping token expiry).
     */
    protected static array $alertRoles = ['superadmin', 'Admin'];

    /**
     * Users with an admin-level role, who should receive system alerts.
     */
    public static function admins(): Collection
    {
        return User::role(self::$alertRoles)->get();
    }
}
