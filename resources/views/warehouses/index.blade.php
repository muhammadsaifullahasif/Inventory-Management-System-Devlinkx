@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Warehouses</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Warehouses</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add warehouses')
                    <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Warehouse</span>
                    </a>
                    @endcan
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
                    <form action="{{ route('warehouses.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Warehouse name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Default Status</label>
                                <select name="is_default" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="1" {{ request('is_default') == '1' ? 'selected' : '' }}>Default Only</option>
                                    <option value="0" {{ request('is_default') == '0' ? 'selected' : '' }}>Non-Default</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('warehouses.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $warehouses->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Warehouses Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete warehouses')
                    @include('partials.bulk-actions-bar', ['itemName' => 'warehouses'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $warehouseColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'racks', 'label' => 'Racks', 'default' => true],
                            ['key' => 'in_stock', 'label' => 'In Stock', 'default' => true],
                            ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'default' => true],
                            ['key' => 'total', 'label' => 'Total', 'default' => true],
                            ['key' => 'quantity', 'label' => 'Total Qty', 'default' => true],
                            ['key' => 'is_default', 'label' => 'Is Default', 'default' => true],
                            ['key' => 'created_at', 'label' => 'Created At', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'warehouseTable', 'cookieName' => 'warehouse_columns', 'columns' => $warehouseColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="warehouseTable">
                        <thead>
                            <tr>
                                @can('delete warehouses')
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
                                        'id' => ['label' => '#', 'column' => 'id', 'style' => '', 'sort' => true],
                                        'name' => ['label' => 'Name', 'column' => 'name', 'style' => '', 'sort' => true],
                                        'racks' => ['label' => 'Racks', 'column' => 'racks', 'style' => '', 'sort' => true],
                                        'in_stock' => ['label' => 'In Stock', 'column' => 'warehouse', 'style' => '', 'sort' => false],
                                        'out_of_stock' => ['label' => 'Out of Stock', 'column' => 'status', 'style' => '', 'sort' => false],
                                        'total' => ['label' => 'Total', 'column' => 'total', 'style' => '', 'sort' => true],
                                        'quantity' => ['label' => 'Total Qty', 'column' => 'quantity', 'style' => '', 'sort' => true],
                                        'is_default' => ['label' => 'Is Default', 'column' => 'received', 'style' => '', 'sort' => false],
                                        'created_at' => ['label' => 'Created At', 'column' => 'created_at', 'style' => '', 'sort' => true],
                                    ];
                                @endphp
                                @foreach ($sortableColumns as $key => $col)
                                    <th data-column="{{ $key }}" @if($col['style']) style="{{ $col['style'] }}" @endif>
                                        @if($col['sort'])
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
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($warehouses as $warehouse)
                                <tr>
                                    @can('delete warehouses')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $warehouse->id }}" data-warehouse-id="{{ $warehouse->id }}">
                                                    <label for="{{ $warehouse->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="warehouse-id-input" value="{{ $warehouse->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $warehouse->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $warehouse->id }}</td>
                                    <td data-column="name">
                                        <a href="{{ route('products.index', ['warehouse_id' => $warehouse->id]) }}" class="fw-semibold text-primary">
                                            {{ $warehouse->name }}
                                        </a>
                                    </td>
                                    <td data-column="racks"><span class="badge bg-soft-secondary text-secondary">{{ $warehouse->racks->count() }}</span></td>
                                    <td data-column="in_stock">
                                        <a href="{{ route('products.index', ['warehouse_id' => $warehouse->id, 'stock_status' => 'in_stock']) }}" class="badge bg-soft-success text-success">
                                            {{ $warehouse->products_count }}
                                        </a>
                                    </td>
                                    <td data-column="out_of_stock">
                                        <a href="{{ route('products.index', ['warehouse_id' => $warehouse->id, 'stock_status' => 'out_of_stock']) }}" class="badge bg-soft-danger text-danger">
                                            {{ $warehouse->out_of_stock_count }}
                                        </a>
                                    </td>
                                    <td data-column="total">
                                        <a href="{{ route('products.index', ['warehouse_id' => $warehouse->id]) }}" class="badge bg-soft-primary text-dark">
                                            {{ $warehouse->products_count + $warehouse->out_of_stock_count }}
                                        </a>
                                    </td>
                                    <td data-column="quantity"><span class="badge bg-soft-info text-info">{{ (int) $warehouse->product_stocks_sum_quantity }}</span></td>
                                    <td data-column="is_default">
                                        @if ($warehouse->is_default)
                                            <span class="badge bg-soft-success text-success">Yes</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">No</span>
                                        @endif
                                    </td>
                                    <td data-column="created_at"><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($warehouse->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            @can('edit warehouses')
                                                <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete warehouses')
                                                <form action="{{ route('warehouses.destroy', $warehouse->id) }}" method="POST" id="warehouse-{{ $warehouse->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $warehouse->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">No warehouses found.</td>
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
                    {{ $warehouses->links('pagination::bootstrap-5') }}
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
                if (confirm('Are you sure to delete this warehouse?')) {
                    $('#warehouse-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>

    @can('delete warehouses')
        @include('partials.bulk-delete-scripts', ['routeName' => 'warehouses.bulk-delete', 'itemName' => 'warehouses'])
    @endcan
@endpush
