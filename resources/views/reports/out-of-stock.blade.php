@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Out of Stock Items Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Out of Stock Items</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('reports.out-of-stock') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">-- All Categories --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" class="form-select form-select-sm">
                                    <option value="">-- All Warehouses --</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ $warehouseId == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock Threshold</label>
                                <input type="number" name="threshold" class="form-control form-control-sm"
                                       value="{{ $threshold }}" min="0" placeholder="0">
                                <small class="text-muted">Show items at or below this qty</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Include Inactive</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="include_inactive"
                                           value="1" {{ $includeInactive ? 'checked' : '' }}>
                                    <label class="form-check-label">Show inactive products</label>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.out-of-stock') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-refresh-cw me-2"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Out of Stock</h6>
                            <h3 class="mb-0 fw-bold text-danger">{{ number_format($summary['total_out_of_stock']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-danger text-white rounded">
                            <i class="feather-alert-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Low Stock</h6>
                            <h3 class="mb-0 fw-bold text-warning">{{ number_format($summary['total_low_stock']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-alert-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-info">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Items</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_items']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-package"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-secondary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Categories Affected</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['categories_affected']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-secondary text-white rounded">
                            <i class="feather-grid"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Out of Stock Items Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-alert-circle me-2"></i>Out of Stock / Low Stock Items</h5>
                <span class="badge bg-danger">{{ $outOfStockItems->count() }} items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-center">Stock</th>
                                <th>Warehouse Details</th>
                                <th>Last Order</th>
                                <th>Last Purchase</th>
                                <th class="text-end">Price</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($outOfStockItems as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.show', $item['product_id']) }}" class="fw-semibold">
                                            {{ $item['product_name'] }}
                                        </a>
                                        @if(!$item['is_active'])
                                            <span class="badge bg-secondary ms-1">Inactive</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $item['product_sku'] }}</code></td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary">{{ $item['category_name'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($item['total_stock'] == 0)
                                            <span class="badge bg-danger">0</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ number_format($item['total_stock'], 2) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(count($item['warehouse_breakdown']) > 0)
                                            @foreach($item['warehouse_breakdown'] as $wh)
                                                <small class="d-block">
                                                    {{ $wh['warehouse_name'] }}
                                                    @if($wh['rack_name'] !== 'N/A')
                                                        / {{ $wh['rack_name'] }}
                                                    @endif
                                                    : <strong>{{ number_format($wh['quantity'], 2) }}</strong>
                                                </small>
                                            @endforeach
                                        @else
                                            <span class="text-muted">No stock records</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['last_order_date'])
                                            <span title="{{ $item['last_order_date']->format('M d, Y H:i') }}">
                                                {{ $item['last_order_date']->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item['last_purchase_date'])
                                            <span title="{{ $item['last_purchase_date']->format('M d, Y H:i') }}">
                                                {{ $item['last_purchase_date']->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($item['price'], 2) }}</td>
                                    <td class="text-center">
                                        @if($item['total_stock'] == 0)
                                            <span class="badge bg-danger">Out of Stock</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Low Stock</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="feather-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                                        <p class="mt-3">All products are well stocked!</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
