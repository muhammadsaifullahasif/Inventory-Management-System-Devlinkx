<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceComparison;
use App\Models\Rack;
use App\Models\SalesChannel;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class MarketResearchController extends Controller
{
    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view products'), ['only' => ['index', 'export']]);
    }

    /**
     * Product-level columns sortable via sort_by on the index listing (one
     * row per product there, so sorting is on the product, not a single
     * competitor row).
     */
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

    /**
     * List products that have captured eBay competitor price comparisons —
     * one row per product, all competitors stacked in a single column —
     * filterable by the same product catalog filters as the Products page.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);

        $sortBy = self::PRODUCT_SORT_COLUMNS[$request->input('sort_by')] ?? 'price_last_compared_at';
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->whereHas('priceComparisons')
            ->with(['priceComparisons' => function ($q) {
                $q->orderBy('rank');
            }])
            ->catalogFilter($request)
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        return view('market-research.index', array_merge(
            compact('products', 'perPage'),
            $this->filterOptions()
        ));
    }

    /**
     * Export the filtered comparisons to Excel (one row per competitor).
     */
    public function export(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $comparisons = $this->buildQuery($request)->select('product_price_comparisons.*')->get();

        $data = $comparisons->map(function (ProductPriceComparison $comparison) {
            return [
                'product_name' => $comparison->product->name ?? 'Deleted product',
                'our_price' => $comparison->product->price ?? null,
                'rank' => $comparison->rank,
                'competitor_seller' => $comparison->competitor_seller,
                'competitor_price' => $comparison->competitor_price,
                'currency' => $comparison->currency,
                'items_sold_last_month' => $comparison->items_sold_last_month,
                'captured_at' => $comparison->captured_at,
                'listing_url' => $comparison->listing_url,
            ];
        })->all();

        $columns = [
            'product_name' => ['label' => 'Product', 'field' => 'product_name'],
            'our_price' => ['label' => 'Our Price', 'field' => 'our_price', 'format' => 'currency'],
            'rank' => ['label' => 'Rank', 'field' => 'rank'],
            'competitor_seller' => ['label' => 'Competitor', 'field' => 'competitor_seller'],
            'competitor_price' => ['label' => 'Competitor Price', 'field' => 'competitor_price', 'format' => 'decimal'],
            'currency' => ['label' => 'Currency', 'field' => 'currency'],
            'items_sold_last_month' => ['label' => 'Units Sold', 'field' => 'items_sold_last_month'],
            'captured_at' => ['label' => 'Captured At', 'field' => 'captured_at', 'format' => 'date'],
            'listing_url' => ['label' => 'Listing URL', 'field' => 'listing_url'],
        ];

        $export = new ReportExport($data, $columns, array_keys($columns), 'Market Research');

        return Excel::download($export, 'market_research_' . date('Y-m-d_His') . '.xlsx');
    }

    /**
     * Shared filtered + sorted base query for both the index listing and export.
     */
    protected function buildQuery(Request $request)
    {
        $sortBy = $request->input('sort_by', 'captured_at');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = ProductPriceComparison::query()
            ->with('product:id,name,product_image,price')
            ->whereHas('product', function ($q) use ($request) {
                $q->catalogFilter($request);
            });

        if (isset(self::PRODUCT_SORT_COLUMNS[$sortBy])) {
            $query->join('products', 'products.id', '=', 'product_price_comparisons.product_id')
                ->orderBy(self::PRODUCT_SORT_COLUMNS[$sortBy], $sortOrder);
        } elseif (isset(self::COMPARISON_SORT_COLUMNS[$sortBy])) {
            $query->orderBy(self::COMPARISON_SORT_COLUMNS[$sortBy], $sortOrder);
        } else {
            $query->orderByDesc('product_price_comparisons.captured_at');
        }

        return $query->orderBy('product_price_comparisons.rank');
    }

    protected function filterOptions(): array
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
