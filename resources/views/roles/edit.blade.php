@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Role</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles</a></li>
                <li class="breadcrumb-item">Edit Role</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('roles.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Roles</span>
                    </a>
                    @can('add roles')
                        <a href="{{ route('roles.create') }}" class="btn btn-primary">
                            <i class="feather-plus me-2"></i>
                            <span>Add Role</span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@push('styles')
<style>
    .permission-category {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .permission-category-header {
        background-color: #f8f9fa;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e9ecef;
        border-radius: 8px 8px 0 0;
        cursor: pointer;
    }
    .permission-category-header:hover {
        background-color: #e9ecef;
    }
    .permission-category-body {
        padding: 1rem;
    }
    .category-checkbox-label {
        font-weight: 600;
        font-size: 1rem;
    }
    .custom-control-label {
        font-size: 0.875rem;
    }
</style>
@endpush

@section('content')
<div class="col-12">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('roles.update', $role->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $role->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Role Name" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Permissions</label>

                    @php
                        // Group permissions by category from database
                        $groupedPermissions = $permissions->groupBy(function($permission) {
                            return $permission->category ?: 'Uncategorized';
                        })->sortKeys();
                    @endphp

                    <div class="row">
                        @foreach($groupedPermissions as $category => $categoryPermissions)
                            @php
                                $categoryKey = \Illuminate\Support\Str::slug($category);
                            @endphp
                            <div class="col-md-6">
                                <div class="permission-category">
                                    <div class="permission-category-header d-flex align-items-center">
                                        <div class="item-checkbox">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox"
                                                       id="category-{{ $categoryKey }}"
                                                       class="custom-control-input category-checkbox"
                                                       data-category="{{ $categoryKey }}">
                                                <label for="category-{{ $categoryKey }}" class="custom-control-label category-checkbox-label">
                                                    {{ $category }}
                                                    <span class="badge bg-soft-primary text-primary ms-2">{{ $categoryPermissions->count() }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="permission-category-body">
                                        <div class="row">
                                            @foreach($categoryPermissions as $perm)
                                                <div class="col-6 mb-1">
                                                    <div class="item-checkbox ms-1">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox"
                                                                   id="perm-{{ $perm->id }}"
                                                                   value="{{ $perm->name }}"
                                                                   name="permission[]"
                                                                   class="custom-control-input checkbox perm-checkbox"
                                                                   data-category="{{ $categoryKey }}"
                                                                   {{ $hasPermissions->contains($perm->name) ? 'checked' : '' }}>
                                                            <label for="perm-{{ $perm->id }}" class="custom-control-label">
                                                                {{ ucwords(str_replace(['-', '_'], ' ', $perm->name)) }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Update Role
                    </button>
                    <a href="{{ route('roles.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Category checkbox - check/uncheck all permissions in category
    $('.category-checkbox').on('change', function() {
        var category = $(this).data('category');
        var isChecked = $(this).is(':checked');
        $('.perm-checkbox[data-category="' + category + '"]').prop('checked', isChecked);
    });

    // Individual permission checkbox - update category checkbox state
    $('.perm-checkbox').on('change', function() {
        var category = $(this).data('category');
        updateCategoryCheckbox(category);
    });

    // Function to update category checkbox state
    function updateCategoryCheckbox(category) {
        var totalInCategory = $('.perm-checkbox[data-category="' + category + '"]').length;
        var checkedInCategory = $('.perm-checkbox[data-category="' + category + '"]:checked').length;

        var categoryCheckbox = $('.category-checkbox[data-category="' + category + '"]');

        if (checkedInCategory === 0) {
            categoryCheckbox.prop('checked', false);
            categoryCheckbox.prop('indeterminate', false);
        } else if (checkedInCategory === totalInCategory) {
            categoryCheckbox.prop('checked', true);
            categoryCheckbox.prop('indeterminate', false);
        } else {
            categoryCheckbox.prop('checked', false);
            categoryCheckbox.prop('indeterminate', true);
        }
    }

    // Initialize category checkbox states on page load
    $('.category-checkbox').each(function() {
        var category = $(this).data('category');
        updateCategoryCheckbox(category);
    });
});
</script>
@endpush
