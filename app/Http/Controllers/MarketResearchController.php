<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rack;
use App\Models\SalesChannel;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class MarketResearchController extends Controller
{
    private const PRODUCT_SORT_COLUMNS = [
        'product_name' => 'name',
        'our_price' => 'price',
        'price_last_compared_at' => 'price_last_compared_at',
    ];

    private const COMPARISON_SORT_COLUMNS = [
        'rank' => 'product_price_comparisons.rank',
        'competitor_seller' => 'product_price_comparisons.competitor_seller',
        'competitor_price' => 'product_price_comparisons.competitor_price',
        'items_sold_last_month' => 'product_price_comparisons.items_sold_last_month',
        'captured_at' => 'product_price_comparisons.captured_at',
    ];

    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view products'), ['only' => ['index', 'export']]);
    }

    public function index(Request $request)
    {
        $sortBy = self::PRODUCT_SORT_COLUMNS[$request->get('sort_by')] ?? 'price_last_compared_at';
        $sortOrder = $request->get('sort_order') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->get('per_page', 15);

        $products = Product::query()
            ->whereHas('priceComparisons')
            ->with(['priceComparisons' => fn ($q) => $q->orderBy('rank'), 'category', 'brand'])
            ->catalogFilter($request)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return view('market-research.index', array_merge(
            ['products' => $products],
            $this->filterOptions()
        ));
    }

    public function export(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $rows = $this->buildQuery($request)->get();

        $data = $rows->map(fn ($row) => [
            'product_name' => $row->name,
            'our_price' => $row->price,
            'competitor_seller' => $row->competitor_seller,
            'competitor_price' => $row->competitor_price,
            'currency' => $row->currency,
            'items_sold_last_month' => $row->items_sold_last_month,
            'rank' => $row->rank,
            'captured_at' => $row->captured_at,
        ])->toArray();

        $columns = [
            'product_name' => ['label' => 'Product'],
            'our_price' => ['label' => 'Our Price', 'format' => 'currency'],
            'competitor_seller' => ['label' => 'Competitor'],
            'competitor_price' => ['label' => 'Competitor Price', 'format' => 'currency'],
            'items_sold_last_month' => ['label' => 'Units Sold'],
            'rank' => ['label' => 'Rank'],
            'captured_at' => ['label' => 'Captured At', 'format' => 'date'],
        ];

        $export = new ReportExport($data, $columns, array_keys($columns), 'Market Research');

        return Excel::download($export, 'market-research_' . date('Y-m-d_His') . '.xlsx');
    }

    private function buildQuery(Request $request)
    {
        $sortKey = $request->get('sort_by');

        $query = Product::query()
            ->join('product_price_comparisons', 'products.id', '=', 'product_price_comparisons.product_id')
            ->select('products.*', 'product_price_comparisons.*')
            ->catalogFilter($request);

        if (isset(self::COMPARISON_SORT_COLUMNS[$sortKey])) {
            $query->orderBy(self::COMPARISON_SORT_COLUMNS[$sortKey], $request->get('sort_order', 'desc'));
        } else {
            $query->orderBy(self::PRODUCT_SORT_COLUMNS[$sortKey] ?? 'price_last_compared_at', $request->get('sort_order', 'desc'));
        }

        return $query->orderBy('product_price_comparisons.rank');
    }

    private function filterOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'salesChannels' => SalesChannel::where('active_status', 1)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'racks' => Rack::with('warehouse')->orderBy('name')->get(),
        ];
    }
}
