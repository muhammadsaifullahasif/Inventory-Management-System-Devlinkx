@extends('layouts.app')

@push('styles')
<style>
    .sortable-header {
        cursor: pointer;
        white-space: nowrap;
        transition: color 0.15s ease;
    }
    .sortable-header:hover {
        color: var(--bs-primary) !important;
    }
    .sortable-header.active {
        color: var(--bs-primary) !important;
        font-weight: 600;
    }
    .sort-arrows {
        display: inline-flex;
        align-items: center;
    }
    .sort-arrows i {
        line-height: 1;
    }
</style>
@endpush

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Market Research</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Market Research</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('market-research.export', request()->query()) }}" class="btn btn-success">
                        <i class="feather-download me-2"></i>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
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
                                <label class="form-label">Stock Status</label>
                                <select name="stock_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                                    <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
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
                            <div class="col-md-1">
                                <label class="form-label">Product Type</label>
                                <select name="product_type" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="regular" {{ request('product_type') == 'regular' ? 'selected' : '' }}>Regular</option>
                                    <option value="bundle" {{ request('product_type') == 'bundle' ? 'selected' : '' }}>Bundle</option>
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
                            <div class="col-md-2 offset-md-4 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $comparisons->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparisons Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-top mb-0">
                        <thead>
                            <tr>
                                @php
                                    $currentSort = request('sort_by', 'captured_at');
                                    $currentOrder = request('sort_order', 'desc');
                                    $sortableColumns = [
                                        'product_name' => ['label' => 'Product', 'column' => 'product_name'],
                                        'our_price' => ['label' => 'Our Price', 'column' => 'our_price'],
                                        'rank' => ['label' => 'Rank', 'column' => 'rank'],
                                        'competitor_seller' => ['label' => 'Competitor', 'column' => 'competitor_seller'],
                                        'competitor_price' => ['label' => 'Competitor Price', 'column' => 'competitor_price'],
                                        'items_sold_last_month' => ['label' => 'Units Sold', 'column' => 'items_sold_last_month'],
                                        'captured_at' => ['label' => 'Captured', 'column' => 'captured_at'],
                                    ];
                                @endphp
                                @foreach($sortableColumns as $key => $col)
                                    <th data-column="{{ $key }}">
                                        @php
                                            $isActive = $currentSort === $col['column'];
                                            $nextOrder = ($isActive && $currentOrder === 'asc') ? 'desc' : 'asc';
                                            $sortUrl = request()->fullUrlWithQuery(['sort_by' => $col['column'], 'sort_order' => $nextOrder]);
                                        @endphp
                                        <a href="{{ $sortUrl }}" class="d-flex align-items-center text-dark text-decoration-none sortable-header {{ $isActive ? 'active' : '' }}">
                                            {{ $col['label'] }}
                                            <span class="sort-arrows ms-1">
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
                                <th class="text-end">Listing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($comparisons as $comparison)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($comparison->product)
                                                @if($comparison->product->getImageUrl())
                                                    <img src="{{ $comparison->product->getImageUrl() }}" alt="{{ $comparison->product->name }}" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                @else
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                        <i class="feather-image text-muted"></i>
                                                    </div>
                                                @endif
                                                <a href="{{ route('products.edit', $comparison->product->id) }}" class="text-dark fw-semibold text-decoration-none">
                                                    {{ $comparison->product->name }}
                                                </a>
                                            @else
                                                <span class="text-muted">Deleted product</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        {{ $comparison->product ? '$' . number_format($comparison->product->price, 2) : '-' }}
                                    </td>
                                    <td>
                                        <span class="badge bg-light-primary">#{{ $comparison->rank }}</span>
                                    </td>
                                    <td>{{ $comparison->competitor_seller }}</td>
                                    <td class="fw-semibold">
                                        {{ $comparison->currency }} {{ number_format($comparison->competitor_price, 2) }}
                                    </td>
                                    <td>{{ $comparison->items_sold_last_month }}</td>
                                    <td class="text-muted fs-12">
                                        {{ $comparison->captured_at?->format('M d, Y H:i') }}
                                    </td>
                                    <td class="text-end">
                                        @if($comparison->listing_url)
                                            <a href="{{ $comparison->listing_url }}" target="_blank" class="btn btn-sm btn-light-brand">
                                                <i class="feather-external-link"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No price comparison data captured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div>
                    @include('partials.per-page-dropdown', ['perPage' => $perPage])
                </div>
                <div>
                    {{ $comparisons->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#warehouse_id').on('change', function() {
            var warehouseId = $(this).val();
            var $rackSelect = $('#rack_id');

            $rackSelect.find('option').show();

            if (warehouseId) {
                $rackSelect.find('option').each(function() {
                    var optionWarehouse = $(this).data('warehouse');
                    if (optionWarehouse && optionWarehouse != warehouseId) {
                        $(this).hide();
                        if ($(this).is(':selected')) {
                            $rackSelect.val('');
                        }
                    }
                });
            }
        });

        $('#warehouse_id').trigger('change');
    });
</script>
@endpush
