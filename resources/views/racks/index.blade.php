@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Racks</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Racks</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('racks.label.bulk-form') }}" class="btn btn-light-brand">
                        <i class="feather-printer me-2"></i>
                        <span>Print Labels</span>
                    </a>
                    @can('add racks')
                    <a href="{{ route('racks.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Rack</span>
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
                    <form action="{{ route('racks.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Rack name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Warehouse</label>
                                <select name="warehouse_id" class="form-select form-select-sm">
                                    <option value="">All Warehouses</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Default Status</label>
                                <select name="is_default" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="1" {{ request('is_default') == '1' ? 'selected' : '' }}>Default Only</option>
                                    <option value="0" {{ request('is_default') == '0' ? 'selected' : '' }}>Non-Default</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('racks.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $racks->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Racks Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete racks')
                    @include('partials.bulk-actions-bar', ['itemName' => 'racks'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $rackColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'warehouse', 'label' => 'Warehouse', 'default' => true],
                            ['key' => 'is_default', 'label' => 'Is Default', 'default' => true],
                            ['key' => 'in_stock', 'label' => 'In Stock', 'default' => true],
                            ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'default' => true],
                            ['key' => 'total', 'label' => 'Total', 'default' => true],
                            ['key' => 'quantity', 'label' => 'Total Qty', 'default' => true],
                            ['key' => 'created_at', 'label' => 'Created At', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'rackTable', 'cookieName' => 'rack_columns', 'columns' => $rackColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="rackTable">
                        <thead>
                            <tr>
                                @can('delete racks')
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
                                        'warehouse' => ['label' => 'Warehouse', 'column' => 'warehouse', 'style' => '', 'sort' => true],
                                        'is_default' => ['label' => 'Is Default', 'column' => 'is_default', 'style' => '', 'sort' => false],
                                        'in_stock' => ['label' => 'In Stock', 'column' => 'in_stock', 'style' => '', 'sort' => false],
                                        'out_of_stock' => ['label' => 'Out of Stock', 'column' => 'out_of_stock', 'style' => '', 'sort' => false],
                                        'total' => ['label' => 'Total', 'column' => 'total', 'style' => '', 'sort' => true],
                                        'quantity' => ['label' => 'Total Qty', 'column' => 'quantity', 'style' => '', 'sort' => true],
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
                            @forelse ($racks as $rack)
                                <tr>
                                    @can('delete racks')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $rack->id }}" data-rack-id="{{ $rack->id }}">
                                                    <label for="{{ $rack->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="rack-id-input" value="{{ $rack->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $rack->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $rack->id }}</td>
                                    <td data-column="name">
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id]) }}" class="fw-semibold text-primary">
                                            {{ $rack->name }}
                                        </a>
                                    </td>
                                    <td data-column="warehouse">
                                        <a href="{{ route('products.index', ['warehouse_id' => $rack->warehouse_id]) }}" class="badge bg-soft-secondary text-secondary">
                                            {{ $rack->warehouse->name }}
                                        </a>
                                    </td>
                                    <td data-column="is_default">
                                        @if ($rack->is_default)
                                            <span class="badge bg-soft-success text-success">Yes</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">No</span>
                                        @endif
                                    </td>
                                    <td data-column="in_stock">
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id, 'stock_status' => 'in_stock']) }}" class="badge bg-soft-success text-success">
                                            {{ $rack->products_count }}
                                        </a>
                                    </td>
                                    <td data-column="out_of_stock">
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id, 'stock_status' => 'out_of_stock']) }}" class="badge bg-soft-danger text-danger">
                                            {{ $rack->out_of_stock_count }}
                                        </a>
                                    </td>
                                    <td data-column="total">
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id]) }}" class="badge bg-soft-primary text-dark">
                                            {{ $rack->products_count + $rack->out_of_stock_count }}
                                        </a>
                                    </td>
                                    <td data-column="quantity"><span class="badge bg-soft-info text-info">{{ (int) $rack->rack_stock_sum_quantity }}</span></td>
                                    <td data-column="created_at"><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($rack->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('racks.print-label', $rack->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Print Label">
                                                <i class="feather-printer"></i>
                                            </a>
                                            @can('edit racks')
                                                <a href="{{ route('racks.edit', $rack->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete racks')
                                                <form action="{{ route('racks.destroy', $rack->id) }}" method="POST" id="rack-{{ $rack->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $rack->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">No racks found.</td>
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
                    {{ $racks->links('pagination::bootstrap-5') }}
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
                if (confirm('Are you sure to delete this rack?')) {
                    $('#rack-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>

    @can('delete racks')
        @include('partials.bulk-delete-scripts', ['routeName' => 'racks.bulk-delete', 'itemName' => 'racks'])
    @endcan
@endpush
