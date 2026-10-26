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
        'shipper_name',
        'shipper_address',
        'shipper_city',
        'shipper_state',
        'shipper_postal_code',
        'shipper_country',
        'shipper_phone',
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

    // Secrets: encrypted at rest with APP_KEY, and kept out of toArray()/JSON
    // (which also keeps them out of the audit log, see AuditLogger::filterFields).
    protected $hidden = [
        'credentials',
        'authorization_code',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'authorization_code' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'is_sandbox'            => 'boolean',
        'is_default'            => 'boolean',
        'is_address_validation' => 'boolean',
    ];
}
