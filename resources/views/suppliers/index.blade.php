@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Suppliers</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Suppliers</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add suppliers')
                    <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Supplier</span>
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
                    <form action="{{ route('suppliers.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, email, company..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="active_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="1" {{ request('active_status') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ request('active_status') == '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control form-control-sm" placeholder="City..." value="{{ request('city') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('suppliers.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $suppliers->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Suppliers Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete suppliers')
                    @include('partials.bulk-actions-bar', ['itemName' => 'suppliers'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $supplierColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'email', 'label' => 'Email', 'default' => true],
                            ['key' => 'phone', 'label' => 'Phone', 'default' => true],
                            ['key' => 'status', 'label' => 'Status', 'default' => true],
                            ['key' => 'created_at', 'label' => 'Created At', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'supplierTable', 'cookieName' => 'supplier_columns', 'columns' => $supplierColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="supplierTable">
                        <thead>
                            <tr>
                                @can('delete suppliers')
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
                                        'email' => ['label' => 'Email', 'column' => 'email', 'style' => '', 'sort' => true],
                                        'phone' => ['label' => 'Phone', 'column' => 'phone', 'style' => '', 'sort' => true],
                                        'status' => ['label' => 'Status', 'column' => 'status', 'style' => '', 'sort' => false],
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
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    @can('delete suppliers')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $supplier->id }}" data-supplier-id="{{ $supplier->id }}">
                                                    <label for="{{ $supplier->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="supplier-id-input" value="{{ $supplier->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $supplier->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $supplier->id }}</td>
                                    <td data-column="name"><span class="fw-semibold">{{ (($supplier->last_name != '') ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name) }}</span></td>
                                    <td data-column="email"><a href="mailto:{{ $supplier->email }}" class="text-primary">{{ $supplier->email }}</a></td>
                                    <td data-column="phone">{{ $supplier->phone }}</td>
                                    <td data-column="status">
                                        @if($supplier->active_status == 1)
                                            <span class="badge bg-soft-success text-success">Active</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td data-column="created_at"><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($supplier->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            @can('edit suppliers')
                                                <a href="{{ route('suppliers.edit', $supplier->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete suppliers')
                                                <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" id="supplier-{{ $supplier->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $supplier->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No suppliers found.</td>
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
                    {{ $suppliers->links('pagination::bootstrap-5') }}
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
                    $('#supplier-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>

    @can('delete suppliers')
        @include('partials.bulk-delete-scripts', ['routeName' => 'suppliers.bulk-delete', 'itemName' => 'suppliers'])
    @endcan
@endpush
