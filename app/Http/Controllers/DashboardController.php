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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Date ranges
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // Summary Statistics
        $stats = $this->getSummaryStats($today, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth);

        // Sales Chart Data (Last 30 days)
        $salesChartData = $this->getSalesChartData();

        // Orders by Status
        $ordersByStatus = $this->getOrdersByStatus();

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
            'monthlyComparison'
        ));
    }

    protected function getSummaryStats($today, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth)
    {
        // Total Products
        $totalProducts = Product::where('active_status', '1')->where('delete_status', '0')->count();

        // Total Orders Today
        $ordersToday = Order::whereDate('created_at', $today)->count();

        // Total Orders This Month
        $ordersThisMonth = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $ordersLastMonth = Order::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $ordersGrowth = $ordersLastMonth > 0 ? round((($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth) * 100, 1) : 100;

        // Revenue Today
        $revenueToday = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');

        // Revenue This Month
        $revenueThisMonth = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('payment_status', 'paid')
            ->sum('total');
        $revenueLastMonth = Order::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->where('payment_status', 'paid')
            ->sum('total');
        $revenueGrowth = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 100;

        // Pending Orders
        $pendingOrders = Order::where('order_status', 'pending')->count();

        // Processing Orders
        $processingOrders = Order::where('order_status', 'processing')->count();

        // Shipped Orders
        $shippedOrders = Order::where('order_status', 'shipped')->count();

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
        $dates = collect();
        $revenues = collect();
        $orders = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dates->push($date->format('M d'));

            $dayRevenue = Order::whereDate('created_at', $date)
                ->where('payment_status', 'paid')
                ->sum('total');
            $revenues->push(round($dayRevenue, 2));

            $dayOrders = Order::whereDate('created_at', $date)->count();
            $orders->push($dayOrders);
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
        $months = collect();
        $revenues = collect();
        $orders = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $months->push($date->format('M Y'));

            $monthRevenue = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->where('payment_status', 'paid')
                ->sum('total');
            $revenues->push(round($monthRevenue, 2));

            $monthOrders = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
            $orders->push($monthOrders);
        }

        return [
            'labels' => $months->toArray(),
            'revenue' => $revenues->toArray(),
            'orders' => $orders->toArray(),
        ];
    }
}
