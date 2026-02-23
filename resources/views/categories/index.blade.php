@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Categories</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Categories</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add categories')
                    <a href="{{ route('categories.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Category</span>
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
                    <form action="{{ route('categories.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Category name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Parent Category</label>
                                <select name="parent_id" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="none" {{ request('parent_id') == 'none' ? 'selected' : '' }}>No Parent (Top Level)</option>
                                    @foreach($parentCategories as $parent)
                                        <option value="{{ $parent->id }}" {{ request('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('categories.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $categories->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Parent Category</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td><span class="fw-semibold">{{ $category->name }}</span></td>
                                    <td>
                                        @if($category->parent_category)
                                            <span class="badge bg-soft-info text-info">{{ $category->parent_category->name }}</span>
                                        @else
                                            <span class="text-muted fs-12">-</span>
                                        @endif
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($category->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            {{-- <div class="dropdown">
                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-expanded="false">
                                                    <i class="feather feather-more-horizontal"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a href="{{ route('categories.edit', $category->id) }}" class="dropdown-item">
                                                            <i class="feather feather-edit-3 me-2"></i>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST" id="category-{{ $category->id }}-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                        </form>
                                                        <a href="javascript:void(0)" data-id="{{ $category->id }}" class="dropdown-item delete-btn text-danger">
                                                            <i class="feather feather-trash-2 me-2"></i>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div> --}}
                                            @can('edit categories')
                                            <a href="{{ route('categories.edit', $category->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                <i class="feather-edit-3"></i>
                                            </a>
                                            @endcan
                                            @can('delete categories')
                                            <form action="{{ route('categories.destroy', $category->id) }}" method="POST" id="category-{{ $category->id }}-delete-form">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <a href="javascript:void(0)" data-id="{{ $category->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No categories found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($categories->hasPages())
            <div class="card-footer">
                {{ $categories->links('pagination::bootstrap-5') }}
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
                if (confirm('Are you sure to delete the record?')) {
                    $('#category-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
@endpush
