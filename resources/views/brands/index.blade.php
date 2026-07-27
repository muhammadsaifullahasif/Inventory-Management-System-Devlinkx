@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Brands</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Brands</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add brands')
                    <a href="{{ route('brands.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Brand</span>
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
                    <form action="{{ route('brands.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Brand name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('brands.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $brands->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Brands Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete brands')
                    @include('partials.bulk-actions-bar', ['itemName' => 'brands'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $brandColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'created_at', 'label' => 'Created At', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'brandTable', 'cookieName' => 'brand_columns', 'columns' => $brandColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="brandTable">
                        <thead>
                            <tr>
                                @can('delete brands')
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
                                        'name' => ['label' => 'Name', 'column' => 'name', 'style' => ''],
                                        'created_at' => ['label' => 'Created At', 'column' => 'created_at', 'style' => ''],
                                    ];
                                @endphp
                                @foreach ($sortableColumns as $key => $col)
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
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($brands as $brand)
                                <tr>
                                    @can('delete brands')
                                        <td class="ps-3">
                                            <div class="item-checkbox ms-1">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $brand->id }}" data-brand-id="{{ $brand->id }}">
                                                    <label for="{{ $brand->id }}" class="custom-control-label"></label>
                                                    <input type="hidden" class="brand-id-input" value="{{ $brand->id }}" disabled>
                                                </div>
                                            </div>
                                            {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $brand->id }}"> --}}
                                        </td>
                                    @endcan
                                    <td data-column="id">{{ $brand->id }}</td>
                                    <td data-column="name"><span class="fw-semibold">{{ $brand->name }}</span></td>
                                    <td data-column="created_at"><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($brand->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            {{-- <div class="dropdown">
                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-expanded="false">
                                                    <i class="feather feather-more-horizontal"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a href="{{ route('brands.edit', $brand->id) }}" class="dropdown-item">
                                                            <i class="feather feather-edit-3 me-2"></i>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('brands.destroy', $brand->id) }}" method="POST" id="brand-{{ $brand->id }}-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                        </form>
                                                        <a href="javascript:void(0)" data-id="{{ $brand->id }}" class="dropdown-item delete-btn text-danger">
                                                            <i class="feather feather-trash-2 me-2"></i>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div> --}}
                                            @can('edit brands')
                                            <a href="{{ route('brands.edit', $brand->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                <i class="feather-edit-3"></i>
                                            </a>
                                            @endcan
                                            @can('delete brands')
                                            <form action="{{ route('brands.destroy', $brand->id) }}" method="POST" id="brand-{{ $brand->id }}-delete-form">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <a href="javascript:void(0)" data-id="{{ $brand->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No brands found.</td>
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
                    {{ $brands->links('pagination::bootstrap-5') }}
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
                    $('#brand-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>

    @can('delete brands')
        @include('partials.bulk-delete-scripts', ['routeName' => 'brands.bulk-delete', 'itemName' => 'brands'])
    @endcan
@endpush
