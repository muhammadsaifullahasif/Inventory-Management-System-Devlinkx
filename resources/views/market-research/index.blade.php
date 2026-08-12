@extends('layouts.app')

@section('header')
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Market Research</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Market Research</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="javascript:void(0)" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('market-research.export', request()->query()) }}" class="btn btn-success">
                        <i class="feather-download me-2"></i>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="javascript:void(0)" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('market-research.index') }}" method="GET" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SKU, Barcode..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Brand</label>
                                <select name="brand_id" class="form-select form-select-sm">
                                    <option value="">All Brands</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sales Channel</label>
                                <select name="sales_channel_id" class="form-select form-select-sm">
                                    <option value="">All Channels</option>
                                    @foreach($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock Status</label>
                                <select name="stock_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                                    <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-2">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" id="warehouse_id" class="form-select form-select-sm">
                                    <option value="">All Warehouses</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Rack</label>
                                <select name="rack_id" id="rack_id" class="form-select form-select-sm">
                                    <option value="">All Racks</option>
                                    @foreach($racks as $rack)
                                        <option value="{{ $rack->id }}" data-warehouse="{{ $rack->warehouse_id }}" {{ request('rack_id') == $rack->id ? 'selected' : '' }}>{{ $rack->name }} ({{ $rack->warehouse->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('market-research.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-6 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $products->total() }} products with comparisons</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Market Research Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-top mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Image</th>
                                @php
                                    $currentSort = request('sort_by', 'price_last_compared_at');
                                    $currentOrder = request('sort_order', 'desc');
                                    $sortableColumns = [
                                        'product_name' => 'Product',
                                        'our_price' => 'Our Price',
                                        'price_last_compared_at' => 'Last Compared',
                                    ];
                                @endphp
                                @foreach($sortableColumns as $key => $label)
                                    @php
                                        $isActive = $currentSort === $key;
                                        $nextOrder = ($isActive && $currentOrder === 'asc') ? 'desc' : 'asc';
                                        $sortUrl = request()->fullUrlWithQuery(['sort_by' => $key, 'sort_order' => $nextOrder]);
                                    @endphp
                                    <th>
                                        <a href="{{ $sortUrl }}" class="d-flex align-items-center text-dark text-decoration-none">
                                            {{ $label }}
                                            <span class="ms-1">
                                                @if($isActive)
                                                    @if($currentOrder === 'asc')
                                                        <i class="feather-arrow-up fs-12"></i>
                                                    @else
                                                        <i class="feather-arrow-down fs-12"></i>
                                                    @endif
                                                @else
                                                    <i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>
                                                @endif
                                            </span>
                                        </a>
                                    </th>
                                @endforeach
                                <th>Competitors (ranked by units sold)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>
                                        @if($product->getImageUrl())
                                            <img src="{{ $product->getImageUrl() }}" alt="{{ $product->name }}" class="rounded" style="width:36px;height:36px;object-fit:cover;">
                                        @endif
                                    </td>
                                    <td style="max-width: 260px;">
                                        <div style="white-space: normal; width: 260px; display: block;" class="fw-medium">{{ $product->name }}</div>
                                        <div class="text-muted fs-12">{{ $product->sku }}</div>
                                    </td>
                                    <td>${{ number_format($product->price, 2) }}</td>
                                    <td>
                                        {{ $product->price_last_compared_at ? $product->price_last_compared_at->format('M d, Y H:i') : 'Never' }}
                                    </td>
                                    <td>
                                        @forelse ($product->priceComparisons as $comparison)
                                            <div class="mb-1">
                                                @if($comparison->listing_url)
                                                    <a href="{{ $comparison->listing_url }}" target="_blank" class="fw-semibold text-decoration-none">${{ number_format($comparison->competitor_price, 2) }}</a>
                                                @else
                                                    <span class="fw-semibold">${{ number_format($comparison->competitor_price, 2) }}</span>
                                                @endif
                                                <span class="text-muted fs-11">{{ $comparison->competitor_seller }} ({{ $comparison->items_sold_last_month }} sold)</span>
                                            </div>
                                        @empty
                                            <span class="text-muted fs-12">-</span>
                                        @endforelse
                                        {{-- <table class="table table-sm table-borderless mb-0">
                                            <thead>
                                                <tr class="text-muted fs-12">
                                                    <th>#</th>
                                                    <th>Seller</th>
                                                    <th>Price</th>
                                                    <th>Units Sold</th>
                                                    <th>Listing</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($product->priceComparisons as $comp)
                                                    <tr>
                                                        <td>{{ $comp->rank }}</td>
                                                        <td>{{ $comp->competitor_seller }}</td>
                                                        <td>
                                                            {{ $comp->currency }} {{ number_format($comp->competitor_price, 2) }}
                                                            @if($product->price > 0)
                                                                @php $diff = $comp->competitor_price - $product->price; @endphp
                                                                <span class="fs-12 {{ $diff < 0 ? 'text-danger' : 'text-success' }}">
                                                                    ({{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2) }})
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $comp->items_sold_last_month }}</td>
                                                        <td>
                                                            @if($comp->listing_url)
                                                                <a href="{{ $comp->listing_url }}" target="_blank" rel="noopener">
                                                                    <i class="feather-external-link"></i>
                                                                </a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table> --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No price comparisons captured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div class="text-muted fs-12">
                    Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results
                </div>
                <div>
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        function filterRacksByWarehouse() {
            var warehouseId = $('#warehouse_id').val();
            $('#rack_id option').each(function () {
                var $option = $(this);
                if (!$option.val()) return;
                var optionWarehouse = $option.data('warehouse') ? String($option.data('warehouse')) : '';
                $option.toggle(!warehouseId || optionWarehouse === String(warehouseId));
            });
        }

        $('#warehouse_id').on('change', function () {
            $('#rack_id').val('');
            filterRacksByWarehouse();
        });

        filterRacksByWarehouse();
    });
</script>
@endpush
