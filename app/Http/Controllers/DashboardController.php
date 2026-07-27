<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\SalesChannel;
use App\Models\ProductStock;
use App\Models\DashboardSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // Get widget settings for current user
        $widgetSettings = DashboardSetting::getForUser($userId);

        // Date ranges
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // Orders by Status (computed once, reused by summary stats below)
        $ordersByStatus = $this->getOrdersByStatus();

        // Summary Statistics
        $stats = $this->getSummaryStats($today, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth, $ordersByStatus);

        // Sales Chart Data (Last 30 days)
        $salesChartData = $this->getSalesChartData();

        // Top Selling Products
        $topProducts = $this->getTopSellingProducts();

        // Low Stock Products
        $lowStockProducts = $this->getLowStockProducts();

        // Recent Orders
        $recentOrders = $this->getRecentOrders();

        // Recent Purchases
        $recentPurchases = $this->getRecentPurchases();

        // Sales by Channel
        $salesByChannel = $this->getSalesByChannel();

        // Monthly Comparison
        $monthlyComparison = $this->getMonthlyComparison();

        return view('dashboard', compact(
            'stats',
            'salesChartData',
            'ordersByStatus',
            'topProducts',
            'lowStockProducts',
            'recentOrders',
            'recentPurchases',
            'salesByChannel',
            'monthlyComparison',
            'widgetSettings'
        ));
    }

    /**
     * Get widget settings page
     */
    public function widgetSettings()
    {
        $userId = Auth::id();
        $widgetSettings = DashboardSetting::getForUser($userId);

        return view('dashboard.widget-settings', compact('widgetSettings'));
    }

    /**
     * Update widget settings
     */
    public function updateWidgetSettings(Request $request)
    {
        $userId = Auth::id();
        $widgets = $request->input('widgets', []);

        // Get current settings and update enabled status
        $currentSettings = DashboardSetting::getForUser($userId);

        foreach ($currentSettings as $key => &$widget) {
            $widget['enabled'] = isset($widgets[$key]) && $widgets[$key] === 'on';
        }

        DashboardSetting::updateForUser($userId, $currentSettings);

        return redirect()->route('dashboard')->with('success', 'Dashboard widgets updated successfully.');
    }

    /**
     * Toggle a single widget via AJAX
     */
    public function toggleWidget(Request $request)
    {
        $userId = Auth::id();
        $widgetKey = $request->input('widget');
        $enabled = $request->boolean('enabled');

        $currentSettings = DashboardSetting::getForUser($userId);

        if (isset($currentSettings[$widgetKey])) {
            $currentSettings[$widgetKey]['enabled'] = $enabled;
            DashboardSetting::updateForUser($userId, $currentSettings);

            return response()->json([
                'success' => true,
                'message' => 'Widget updated successfully',
                'widget' => $widgetKey,
                'enabled' => $enabled,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Widget not found',
        ], 404);
    }

    /**
     * Reset widgets to default
     */
    public function resetWidgets()
    {
        $userId = Auth::id();
        $defaultWidgets = DashboardSetting::getDefaultWidgets();

        DashboardSetting::updateForUser($userId, $defaultWidgets);

        return redirect()->route('dashboard')->with('success', 'Dashboard widgets reset to default.');
    }

    protected function getSummaryStats($today, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth, $ordersByStatus = [])
    {
        // Total Products
        $totalProducts = Product::where('active_status', '1')->where('delete_status', '0')->count();

        // Today/this-month/last-month order counts + paid revenue in one scan
        // (range covers startOfLastMonth..endOfMonth, which also contains "today")
        $todayStr = $today->format('Y-m-d');
        $summary = Order::whereBetween('created_at', [$startOfLastMonth, $endOfMonth])
            ->selectRaw(
                "SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as orders_today,
                 SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_this_month,
                 SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_last_month,
                 SUM(CASE WHEN DATE(created_at) = ? AND payment_status = 'paid' THEN total ELSE 0 END) as revenue_today,
                 SUM(CASE WHEN created_at BETWEEN ? AND ? AND payment_status = 'paid' THEN total ELSE 0 END) as revenue_this_month,
                 SUM(CASE WHEN created_at BETWEEN ? AND ? AND payment_status = 'paid' THEN total ELSE 0 END) as revenue_last_month",
                [
                    $todayStr,
                    $startOfMonth, $endOfMonth,
                    $startOfLastMonth, $endOfLastMonth,
                    $todayStr,
                    $startOfMonth, $endOfMonth,
                    $startOfLastMonth, $endOfLastMonth,
                ]
            )
            ->first();

        $ordersToday = (int) $summary->orders_today;
        $ordersThisMonth = (int) $summary->orders_this_month;
        $ordersLastMonth = (int) $summary->orders_last_month;
        $ordersGrowth = $ordersLastMonth > 0 ? round((($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth) * 100, 1) : 100;

        $revenueToday = (float) $summary->revenue_today;
        $revenueThisMonth = (float) $summary->revenue_this_month;
        $revenueLastMonth = (float) $summary->revenue_last_month;
        $revenueGrowth = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 100;

        // Order status counts reused from getOrdersByStatus() — avoids 3 extra queries
        $pendingOrders = (int) ($ordersByStatus['pending'] ?? 0);
        $processingOrders = (int) ($ordersByStatus['processing'] ?? 0);
        $shippedOrders = (int) ($ordersByStatus['shipped'] ?? 0);

        // Total Stock Value
        $totalStockValue = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->where('products.active_status', '1')
            ->where('products.delete_status', '0')
            ->selectRaw('SUM(CAST(product_stocks.quantity AS UNSIGNED) * products.price) as total_value')
            ->value('total_value') ?? 0;

        // Total Purchases This Month
        $purchasesThisMonth = Purchase::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        // Active Sales Channels
        $activeSalesChannels = SalesChannel::whereNotNull('access_token')
            ->where('access_token_expires_at', '>', now())
            ->count();

        // Total Suppliers
        $totalSuppliers = Supplier::where('active_status', '1')->count();

        // Total Categories
        $totalCategories = Category::count();

        // Total Warehouses
        $totalWarehouses = Warehouse::count();

        return [
            'total_products' => $totalProducts,
            'orders_today' => $ordersToday,
            'orders_this_month' => $ordersThisMonth,
            'orders_growth' => $ordersGrowth,
            'revenue_today' => $revenueToday,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_growth' => $revenueGrowth,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'shipped_orders' => $shippedOrders,
            'total_stock_value' => $totalStockValue,
            'purchases_this_month' => $purchasesThisMonth,
            'active_sales_channels' => $activeSalesChannels,
            'total_suppliers' => $totalSuppliers,
            'total_categories' => $totalCategories,
            'total_warehouses' => $totalWarehouses,
        ];
    }

    protected function getSalesChartData()
    {
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Single query - group by date
        $salesData = Order::selectRaw('DATE(created_at) as date,
                                       COUNT(*) as order_count,
                                       SUM(CASE WHEN payment_status = "paid" THEN total ELSE 0 END) as revenue')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill all 30 days
        $dates = collect();
        $revenues = collect();
        $orders = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->format('Y-m-d');

            $dates->push($date->format('M d'));

            if ($salesData->has($dateStr)) {
                $revenues->push(round($salesData[$dateStr]->revenue, 2));
                $orders->push($salesData[$dateStr]->order_count);
            } else {
                $revenues->push(0);
                $orders->push(0);
            }
        }

        return [
            'labels' => $dates->toArray(),
            'revenue' => $revenues->toArray(),
            'orders' => $orders->toArray(),
        ];
    }

    protected function getOrdersByStatus()
    {
        return Order::selectRaw('order_status, COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();
    }

    protected function getTopSellingProducts($limit = 10)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'products.price',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.price')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();
    }

    protected function getLowStockProducts($threshold = 10, $limit = 10)
    {
        return DB::table('products')
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.active_status', '1')
            ->where('products.delete_status', '0')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('COALESCE(SUM(CAST(product_stocks.quantity AS UNSIGNED)), 0) as total_stock')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->havingRaw('COALESCE(SUM(CAST(product_stocks.quantity AS UNSIGNED)), 0) <= ?', [$threshold])
            ->orderBy('total_stock')
            ->limit($limit)
            ->get();
    }

    protected function getRecentOrders($limit = 10)
    {
        return Order::with(['salesChannel', 'items'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function getRecentPurchases($limit = 5)
    {
        return Purchase::with(['supplier', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function getSalesByChannel()
    {
        return Order::join('sales_channels', 'orders.sales_channel_id', '=', 'sales_channels.id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                'sales_channels.name',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.total) as total_revenue')
            )
            ->groupBy('sales_channels.id', 'sales_channels.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    protected function getMonthlyComparison()
    {
        $rangeStart = Carbon::now()->subMonths(5)->startOfMonth();
        $rangeEnd = Carbon::now()->endOfMonth();

        // Single query - group by year-month
        $monthlyData = Order::selectRaw(
                "DATE_FORMAT(created_at, '%Y-%m') as ym,
                 COUNT(*) as order_count,
                 SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue"
            )
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $months = collect();
        $revenues = collect();
        $orders = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $ymKey = $date->format('Y-m');

            $months->push($date->format('M Y'));

            if ($monthlyData->has($ymKey)) {
                $revenues->push(round($monthlyData[$ymKey]->revenue, 2));
                $orders->push((int) $monthlyData[$ymKey]->order_count);
            } else {
                $revenues->push(0);
                $orders->push(0);
            }
        }

        return [
            'labels' => $months->toArray(),
            'revenue' => $revenues->toArray(),
            'orders' => $orders->toArray(),
        ];
    }
}
