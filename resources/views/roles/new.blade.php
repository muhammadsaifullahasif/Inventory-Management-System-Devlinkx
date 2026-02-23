@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Role</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles</a></li>
                <li class="breadcrumb-item">Add Role</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('roles.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Roles</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
<div class="col-12">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('roles.store') }}" method="post">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Role Name" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Permissions</label>
                    <div class="row">
                        @foreach ($permissions as $permission)
                            <div class="col-md-3 mb-2">
                                {{-- <div class="form-check">
                                    <input type="checkbox" id="perm-{{ $permission->id }}" value="{{ $permission->name }}" name="permission[]" class="form-check-input">
                                    <label for="perm-{{ $permission->id }}" class="form-check-label">{{ $permission->name }}</label>
                                </div> --}}
                                <div class="item-checkbox ms-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="perm-{{ $permission->id }}" value="{{ $permission->name }}" name="permission[]" class="custom-control-input checkbox">
                                        <label for="perm-{{ $permission->id }}" class="custom-control-label">{{ $permission->name }}</label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Save Role
                    </button>
                    <a href="{{ route('roles.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
