<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * Predefined permission categories.
     */
    public const CATEGORIES = [
        'Orders' => 'Orders',
        'Products' => 'Products',
        'Purchases' => 'Purchases',
        'Warehouses' => 'Warehouses',
        'Sales Channels' => 'Sales Channels',
        'Suppliers' => 'Suppliers',
        'Shipping' => 'Shipping',
        'Accounting' => 'Accounting',
        'Users & Access' => 'Users & Access',
    ];

    /**
     * Get all predefined categories.
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get categories as options for select box.
     */
    public static function getCategoryOptions(): array
    {
        return self::CATEGORIES;
    }
}
