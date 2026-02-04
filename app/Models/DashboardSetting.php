<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardSetting extends Model
{
    protected $fillable = [
        'user_id',
        'widgets',
    ];

    protected $casts = [
        'widgets' => 'array',
    ];

    /**
     * Default widget configuration
     */
    public static function getDefaultWidgets(): array
    {
        return [
            'revenue_today' => ['enabled' => true, 'order' => 1, 'label' => 'Revenue Today'],
            'revenue_month' => ['enabled' => true, 'order' => 2, 'label' => 'Revenue This Month'],
            'orders_today' => ['enabled' => true, 'order' => 3, 'label' => 'Orders Today'],
            'orders_month' => ['enabled' => true, 'order' => 4, 'label' => 'Orders This Month'],
            'pending_orders' => ['enabled' => true, 'order' => 5, 'label' => 'Pending Orders'],
            'processing_orders' => ['enabled' => true, 'order' => 6, 'label' => 'Processing Orders'],
            'shipped_orders' => ['enabled' => true, 'order' => 7, 'label' => 'Shipped Orders'],
            'stock_value' => ['enabled' => true, 'order' => 8, 'label' => 'Stock Value'],
            'total_products' => ['enabled' => true, 'order' => 9, 'label' => 'Total Products'],
            'active_channels' => ['enabled' => true, 'order' => 10, 'label' => 'Active Channels'],
            'total_suppliers' => ['enabled' => true, 'order' => 11, 'label' => 'Total Suppliers'],
            'purchases_month' => ['enabled' => true, 'order' => 12, 'label' => 'Purchases (Month)'],
            'sales_chart' => ['enabled' => true, 'order' => 13, 'label' => 'Sales Overview Chart'],
            'order_status_chart' => ['enabled' => true, 'order' => 14, 'label' => 'Orders by Status Chart'],
            'monthly_chart' => ['enabled' => true, 'order' => 15, 'label' => 'Monthly Comparison Chart'],
            'top_products' => ['enabled' => true, 'order' => 16, 'label' => 'Top Selling Products'],
            'low_stock' => ['enabled' => true, 'order' => 17, 'label' => 'Low Stock Alert'],
            'recent_orders' => ['enabled' => true, 'order' => 18, 'label' => 'Recent Orders'],
            'sales_by_channel' => ['enabled' => true, 'order' => 19, 'label' => 'Sales by Channel'],
            'recent_purchases' => ['enabled' => true, 'order' => 20, 'label' => 'Recent Purchases'],
        ];
    }

    /**
     * Get user's dashboard settings
     */
    public static function getForUser(int $userId): array
    {
        $setting = self::where('user_id', $userId)->first();

        if (!$setting || empty($setting->widgets)) {
            return self::getDefaultWidgets();
        }

        // Merge with defaults to ensure new widgets are included
        $defaults = self::getDefaultWidgets();
        $userWidgets = $setting->widgets;

        foreach ($defaults as $key => $default) {
            if (!isset($userWidgets[$key])) {
                $userWidgets[$key] = $default;
            }
        }

        return $userWidgets;
    }

    /**
     * Check if a specific widget is enabled
     */
    public static function isWidgetEnabled(int $userId, string $widgetKey): bool
    {
        $widgets = self::getForUser($userId);
        return $widgets[$widgetKey]['enabled'] ?? true;
    }

    /**
     * Update user's widget settings
     */
    public static function updateForUser(int $userId, array $widgets): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId],
            ['widgets' => $widgets]
        );
    }

    /**
     * Relationship to user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
