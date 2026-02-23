@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Permission</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('permissions.index') }}">Permissions</a></li>
                <li class="breadcrumb-item">Edit Permission</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('permissions.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Permissions</span>
                    </a>
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
<div class="col-12">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('permissions.update', $permission->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $permission->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Permission Name" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Update Permission
                    </button>
                    <a href="{{ route('permissions.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
