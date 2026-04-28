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
    .sortable-header:hover .sort-arrows .text-muted {
        opacity: 1 !important;
    }
</style>
@endpush

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Products</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Products</li>
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
                    <a href="{{ route('products.export', request()->query()) }}" class="btn btn-success">
                        <i class="feather-download me-2"></i>
                        <span>Export Excel</span>
                    </a>
                    <a href="{{ route('products.import') }}" class="btn btn-light-brand">
                        <i class="feather-upload me-2"></i>
                        <span>Import</span>
                    </a>
                    <a href="{{ route('products.barcode.bulk-form') }}" class="btn btn-light-brand">
                        <i class="feather-printer me-2"></i>
                        <span>Print Barcodes</span>
                    </a>
                    <a href="{{ route('products.bulk-update.form') }}" class="btn btn-light-brand">
                        <i class="feather-edit me-2"></i>
                        <span>Bulk Update</span>
                    </a>
                    @can('add products')
                    <a href="{{ route('products.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Product</span>
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="javascript:void(0)" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
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
                    <form action="{{ route('products.index') }}" method="GET" id="filterForm">
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
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('products.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $products->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete products')
                    @include('partials.bulk-actions-bar', ['itemName' => 'products'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $productColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'image', 'label' => 'Image', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'price', 'label' => 'Price', 'default' => true],
                            ['key' => 'quantity', 'label' => 'Quantity', 'default' => true],
                            ['key' => 'location', 'label' => 'Location', 'default' => true],
                            ['key' => 'category', 'label' => 'Category', 'default' => true],
                            ['key' => 'sales_channels', 'label' => 'Sales Channels', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'productsTable', 'cookieName' => 'products_columns', 'columns' => $productColumns])
                </div>
            </div>
            {{-- <div class="card-body pb-0 d-flex align-items-center justify-content-between">
            </div> --}}
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-top mb-0" id="productsTable">
                        <thead>
                            <tr>
                                @can('delete products')
                                    <th class="ps-3" style="width: 40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectAll" title="Select all on this page">
                                                <label for="selectAll" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                    </th>
                                @endcan
                                @php
                                    $currentSort = request('sort_by', 'id');
                                    $currentOrder = request('sort_order', 'desc');
                                    $sortableColumns = [
                                        'id' => ['label' => '#', 'column' => 'id', 'style' => ''],
                                        'image' => ['label' => 'Image', 'column' => 'product_image', 'style' => 'width: 60px;'],
                                        'name' => ['label' => 'Name', 'column' => 'name', 'style' => 'max-width: 300px;'],
                                        'price' => ['label' => 'Price', 'column' => 'price', 'style' => ''],
                                        'quantity' => ['label' => 'Quantity', 'column' => 'quantity', 'style' => ''],
                                        'location' => ['label' => 'Location', 'column' => null, 'style' => ''],
                                        'category' => ['label' => 'Category', 'column' => 'category_id', 'style' => ''],
                                        'sales_channels' => ['label' => 'Sales Channels', 'column' => 'sales_channels_count', 'style' => ''],
                                    ];
                                @endphp
                                @foreach($sortableColumns as $key => $col)
                                    <th data-column="{{ $key }}" @if($col['style']) style="{{ $col['style'] }}" @endif>
                                        @if($col['column'])
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
                                        @else
                                            {{ $col['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                                {{-- <th>Created at</th> --}}
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    @can('delete products')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $product->id }}" data-product-id="{{ $product->id }}" value="{{ $product->id }}">
                                                    <label for="{{ $product->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="product-id-input" value="{{ $product->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $product->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $product->id }}</td>
                                    <td data-column="image">
                                        @if($product->getImageUrl())
                                            <img src="{{ $product->getImageUrl() }}" alt="{{ $product->name }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="feather-image text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    {{-- <td>
                                        <div style="max-width: 100px;">
                                            @php
                                                $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
                                                $renderer = new Picqer\Barcode\Renderers\SvgRenderer();
                                                $renderer->setSvgType($renderer::TYPE_SVG_STANDALONE);
                                            @endphp
                                            {!! $renderer->render($barcode, 100, 40) !!}
                                            <span class="d-block fs-11 text-muted mt-1">{{ $product->barcode }}</span>
                                            <div class="hstack gap-2 justify-content-start">
                                                <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Print">
                                                    <i class="feather feather-printer"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </td> --}}
                                    <td data-column="name">
                                        <a href="{{ route('products.edit', $product->id) }}"><span style="white-space: normal; width: 300px; display: block;" class="fw-semibold">{{ $product->name }}</span></a>
                                        <span class="d-block fs-11 text-muted">SKU: {{ $product->sku }}</span>
                                    </td>
                                    <td data-column="price"><span class="fw-semibold">${{ number_format($product->price, 2) }}</span></td>
                                    <td data-column="quantity">
                                        @php
                                            $totalStock = $product->is_bundle
                                                ? $product->available_stock
                                                : $product->product_stocks->sum('quantity');
                                        @endphp
                                        @if($totalStock > 0)
                                            <span class="badge bg-soft-success text-success">{{ $totalStock }}</span>
                                            @if($product->is_bundle)
                                                <span class="badge bg-soft-primary text-primary ms-1">Bundle</span>
                                            @endif
                                        @else
                                            <span class="badge bg-soft-danger text-danger">Out of Stock</span>
                                            @if($product->is_bundle)
                                                <span class="badge bg-soft-primary text-primary ms-1">Bundle</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td data-column="location">
                                        @if($product->is_bundle)
                                            <span class="badge bg-soft-info text-info">Bundle</span>
                                            <span class="text-muted fs-11">({{ $product->bundleComponents->count() }} Components)</span>
                                        @else
                                            @forelse($product->product_stocks as $stock)
                                                <div class="mb-1">
                                                    <span class="badge bg-soft-info text-info">{{ $stock->warehouse->name ?? 'N/A' }}</span>
                                                    <span class="badge bg-soft-secondary text-secondary">{{ $stock->rack->name ?? 'N/A' }}</span>
                                                    <span class="text-muted fs-11">({{ $stock->quantity }})</span>
                                                </div>
                                            @empty
                                                <span class="text-muted fs-12">-</span>
                                            @endforelse
                                        @endif
                                    </td>
                                    <td data-column="category">{{ $product->category->name ?? 'N/A' }}</td>
                                    <td data-column="sales_channels">
                                        @foreach ($product->sales_channels as $sales_channel)
                                            <a href="{{ $sales_channel->pivot->listing_url }}" target="_blank" class="badge bg-soft-primary text-primary me-1">{{ $sales_channel['name'] }}</a>
                                        @endforeach
                                    </td>
                                    {{-- <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($product->created_at)->format('d M, Y') }}</span></td> --}}
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('products.show', $product->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                            <div class="dropdown">
                                                <a href="#" role="button" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-expanded="false">
                                                    <i class="feather feather-more-horizontal"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a href="{{ route('products.edit', $product->id) }}" class="dropdown-item">
                                                            <i class="feather feather-edit-3 me-2"></i>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank" class="dropdown-item">
                                                            <i class="feather feather-printer me-2"></i>
                                                            <span>Print Barcode</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('products.destroy', $product->id) }}" method="POST" id="product-{{ $product->id }}-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                        </form>
                                                        <a href="javascript:void(0)" data-id="{{ $product->id }}" class="dropdown-item delete-btn text-danger">
                                                            <i class="feather feather-trash-2 me-2"></i>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            {{-- <a href="{{ route('products.edit', $product->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                <i class="feather-edit-3"></i>
                                            </a>
                                            <a href="javascript:void(0)" data-id="{{ $product->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </a> --}}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">No products found.</td>
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
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            $(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
                if (confirm('Are you sure to delete the record?')) {
                    $('#product-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });

            // Filter racks based on selected warehouse
            $('#warehouse_id').on('change', function() {
                var warehouseId = $(this).val();
                var $rackSelect = $('#rack_id');
                var currentRackId = '{{ request('rack_id') }}';

                // Show all options first
                $rackSelect.find('option').show();

                if (warehouseId) {
                    // Hide options that don't match the selected warehouse
                    $rackSelect.find('option').each(function() {
                        var optionWarehouse = $(this).data('warehouse');
                        if (optionWarehouse && optionWarehouse != warehouseId) {
                            $(this).hide();
                            // If the currently selected rack is hidden, reset selection
                            if ($(this).is(':selected')) {
                                $rackSelect.val('');
                            }
                        }
                    });
                }
            });

            // Trigger on page load to filter racks if warehouse is pre-selected
            $('#warehouse_id').trigger('change');
        });
    </script>
    @can('delete products')
        @include('partials.bulk-delete-scripts', ['routeName' => 'products.bulk-delete', 'itemName' => 'products'])
    @endcan
@endpush
