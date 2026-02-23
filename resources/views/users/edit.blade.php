@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit User</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                <li class="breadcrumb-item">Edit User</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('users.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Users</span>
                    </a>
                    @can('add users')
                        <a href="{{ route('users.create') }}" class="btn btn-primary">
                            <i class="feather-plus me-2"></i>
                            <span>Add User</span>
                        </a>
                    @endcan
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
            <form action="{{ route('users.update', $user->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="User Name" required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" placeholder="User Email" required>
                        @error('email')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="New Password" autocomplete="new-password">
                    <small class="text-muted">Leave blank if you don't want to change the password.</small>
                    @error('password')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Roles</label>
                    <div class="row">
                        @foreach ($roles as $role)
                            <div class="col-md-3 mb-2">
                                {{-- <div class="form-check">
                                    <input type="checkbox" id="role-{{ $role->id }}" value="{{ $role->name }}" name="role[]" class="form-check-input" {{ $hasRoles->contains($role->id) ? 'checked' : '' }}>
                                    <label for="role-{{ $role->id }}" class="form-check-label">{{ $role->name }}</label>
                                </div> --}}
                                <div class="item-checkbox ms-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" id="role-{{ $role->id }}" value="{{ $role->name }}" name="role[]" class="custom-control-input checkbox" {{ $hasRoles->contains($role->id) ? 'checked' : '' }}>
                                        <label for="role-{{ $role->id }}" class="custom-control-label">{{ $role->name }}</label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Update User
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
