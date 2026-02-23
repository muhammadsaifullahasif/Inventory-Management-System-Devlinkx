@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Brand</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Brands</a></li>
                <li class="breadcrumb-item">Edit Brand</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('brands.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Brands</span>
                    </a>
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
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Brand Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('brands.update', $brand->id) }}" method="post">
                    @csrf
                    @method("PUT")
                    <div class="mb-4">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $brand->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Brand Name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Update Brand
                        </button>
                        <a href="{{ route('brands.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
