<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    protected $fillable = [
        'name',
        'type',
        'credentials',
        'authorization_code',
        'access_token',
        'access_token_expires_at',
        'refresh_token',
        'refresh_token_expires_at',
        'additional_info',
        'account_number',
        'is_sandbox',
        'is_address_validation',
        'api_endpoint',
        'sandbox_endpoint',
        'tracking_url',
        'is_default',
        'default_service',
        'weight_unit',
        'dimension_unit',
        'status',
        'active_status',
        'delete_status',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_sandbox'            => 'boolean',
        'is_default'            => 'boolean',
        'is_address_validation' => 'boolean',
    ];
}
