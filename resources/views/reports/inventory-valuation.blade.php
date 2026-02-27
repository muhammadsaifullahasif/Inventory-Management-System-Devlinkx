@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Inventory Valuation Report</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Inventory Valuation</li>
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
                    <form action="{{ route('reports.inventory-valuation') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
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
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <label class="form-label">Group By</label>
                                <select name="group_by" class="form-select form-select-sm">
                                    <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Product</option>
                                    <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
                                    <option value="warehouse" {{ $groupBy == 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Generate Report
                                </button>
                                <a href="{{ route('reports.inventory-valuation') }}" class="btn btn-light-brand btn-sm">
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
            <div class="card bg-soft-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Products</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_products']) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-primary text-white rounded">
                            <i class="feather-package"></i>
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
                            <h6 class="text-muted mb-1">Total Quantity</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_quantity'], 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-info text-white rounded">
                            <i class="feather-layers"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-soft-success">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-1">Total Inventory Value</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['total_value'], 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-success text-white rounded">
                            <i class="feather-dollar-sign"></i>
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
                            <h6 class="text-muted mb-1">Average Cost/Unit</h6>
                            <h3 class="mb-0 fw-bold">{{ number_format($summary['avg_cost_per_unit'], 4) }}</h3>
                        </div>
                        <div class="avatar-text avatar-lg bg-warning text-white rounded">
                            <i class="feather-trending-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounting Reconciliation Card -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="feather-check-square me-2"></i>Accounting Reconciliation</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center border-end">
                        <h6 class="text-muted mb-2">Physical Inventory Value</h6>
                        <h4 class="fw-bold text-primary">{{ number_format($reconciliation['physical_inventory_value'], 2) }}</h4>
                        <small class="text-muted">Based on ProductStock table</small>
                    </div>
                    <div class="col-md-4 text-center border-end">
                        <h6 class="text-muted mb-2">Accounting Balance</h6>
                        <h4 class="fw-bold text-info">{{ number_format($reconciliation['accounting_balance'], 2) }}</h4>
                        <small class="text-muted">Inventory Asset Account (1201)</small>
                    </div>
                    <div class="col-md-4 text-center">
                        <h6 class="text-muted mb-2">Variance</h6>
                        <h4 class="fw-bold {{ $reconciliation['is_reconciled'] ? 'text-success' : 'text-danger' }}">
                            {{ number_format($reconciliation['variance'], 2) }}
                        </h4>
                        @if($reconciliation['is_reconciled'])
                            <span class="badge bg-success">Reconciled</span>
                        @else
                            <span class="badge bg-danger">Variance Detected</span>
                        @endif
                    </div>
                </div>
                @if(!$reconciliation['is_reconciled'])
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="feather-alert-triangle me-2"></i>
                        <strong>Note:</strong> There is a variance between physical inventory and accounting records.
                        This may be due to: inventory adjustments not yet recorded, manual stock changes, or transactions in progress.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Grouped Data Table -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="feather-bar-chart-2 me-2"></i>
                    Inventory by {{ ucfirst($groupBy) }}
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ ucfirst($groupBy) }}</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-end">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groupedData as $group)
                                <tr>
                                    <td>
                                        <strong>{{ $group['name'] }}</strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary">{{ $group['item_count'] }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($group['quantity'], 2) }}</td>
                                    <td class="text-end">{{ number_format($group['avg_cost'], 4) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($group['total_value'], 2) }}</td>
                                    <td class="text-end">
                                        @php
                                            $percentage = $summary['total_value'] > 0
                                                ? ($group['total_value'] / $summary['total_value']) * 100
                                                : 0;
                                        @endphp
                                        <div class="progress" style="height: 6px; width: 80px; display: inline-block;">
                                            <div class="progress-bar bg-success" style="width: {{ min($percentage, 100) }}%"></div>
                                        </div>
                                        <span class="ms-2">{{ number_format($percentage, 1) }}%</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="feather-package" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No inventory found with the selected filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($groupedData->isNotEmpty())
                            <tfoot>
                                <tr class="table-light">
                                    <td class="fw-bold">Grand Total</td>
                                    <td class="text-center fw-bold">{{ count($inventoryItems) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($summary['total_quantity'], 2) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($summary['avg_cost_per_unit'], 4) }}</td>
                                    <td class="text-end fw-bold">{{ number_format($summary['total_value'], 2) }}</td>
                                    <td class="text-end fw-bold">100%</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Inventory List -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="feather-list me-2"></i>Detailed Inventory List</h5>
                <span class="badge bg-primary">{{ count($inventoryItems) }} items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Warehouse / Rack</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Avg Cost</th>
                                <th class="text-end">Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventoryItems as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.show', $item['product_id']) }}" class="fw-semibold">
                                            {{ $item['product_name'] }}
                                        </a>
                                    </td>
                                    <td><code>{{ $item['product_sku'] }}</code></td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary">{{ $item['category_name'] }}</span>
                                    </td>
                                    <td>
                                        {{ $item['warehouse_name'] }}
                                        @if($item['rack_name'] !== 'N/A')
                                            <small class="text-muted">/ {{ $item['rack_name'] }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($item['quantity'], 2) }}</td>
                                    <td class="text-end">
                                        @if($item['avg_cost'] > 0)
                                            {{ number_format($item['avg_cost'], 4) }}
                                        @else
                                            <span class="text-warning" title="No cost recorded">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($item['total_value'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-package" style="font-size: 3rem;"></i>
                                        <p class="mt-3">No inventory items found.</p>
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
