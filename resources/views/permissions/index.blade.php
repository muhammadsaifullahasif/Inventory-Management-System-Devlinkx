@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Permissions</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Permissions</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add permissions')
                    <a href="{{ route('permissions.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Permission</span>
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
                    <form action="{{ route('permissions.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Permission name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('permissions.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $permissions->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($permissions as $permission)
                                <tr>
                                    <td>{{ $permission->id }}</td>
                                    <td><span class="fw-semibold">{{ $permission->name }}</span></td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($permission->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            @can('edit permissions')
                                                <a href="{{ route('permissions.edit', $permission->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete permissions')
                                                <form action="{{ route('permissions.destroy', $permission->id) }}" method="POST" id="permission-{{ $permission->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $permission->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No permissions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($permissions->hasPages())
            <div class="card-footer">
                {{ $permissions->links('pagination::bootstrap-5') }}
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
                if (confirm('Are you sure to delete this permission?')) {
                    $('#permission-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
@endpush
