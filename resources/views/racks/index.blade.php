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
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Warehouse</th>
                                <th>Is Default</th>
                                <th>Products</th>
                                <th>Total Qty</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($racks as $rack)
                                <tr>
                                    <td>{{ $rack->id }}</td>
                                    <td>
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id]) }}" class="fw-semibold text-primary">
                                            {{ $rack->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('products.index', ['warehouse_id' => $rack->warehouse_id]) }}" class="badge bg-soft-secondary text-secondary">
                                            {{ $rack->warehouse->name }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($rack->is_default)
                                            <span class="badge bg-soft-success text-success">Yes</span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('products.index', ['rack_id' => $rack->id]) }}" class="badge bg-soft-primary text-primary">
                                            {{ $rack->products_count }}
                                        </a>
                                    </td>
                                    <td><span class="badge bg-soft-info text-info">{{ (int) $rack->rack_stock_sum_quantity }}</span></td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($rack->created_at)->format('d M, Y') }}</span></td>
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
                                    <td colspan="8" class="text-center py-4 text-muted">No racks found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($racks->hasPages())
            <div class="card-footer">
                {{ $racks->links('pagination::bootstrap-5') }}
            </div>
            @endif
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
@endpush
