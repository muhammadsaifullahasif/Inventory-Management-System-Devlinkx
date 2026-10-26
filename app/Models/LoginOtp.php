<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginOtp extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'ip_address',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Codes are stored as an HMAC keyed with APP_KEY, never in plain text, so a
     * database read alone can't be used to pass the email verification step.
     * (A plain hash would not do: 6 digits are brute-forced instantly.)
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
