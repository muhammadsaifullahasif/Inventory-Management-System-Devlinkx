@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Product Import</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Product Import</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex d-md-none">
                    <a href="javascript:void(0)" class="page-header-right-close-toggle">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add products')
                    <a href="{{ route('products.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Product</span>
                    </a>
                    @endcan
                </div>
            </div>
            <div class="d-md-none d-flex align-items-center">
                <a href="javascript:void(0)" class="page-header-right-open-toggle">
                    <i class="feather-align-right fs-20"></i>
                </a>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Product Import</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('products.import.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="upload" class="form-label">Upload File: <span class="text-danger">*</span></label>
                        <input type="file" class="form-control @error('upload') is-invalid @enderror" id="upload" name="upload" required>
                        @error('upload')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-upload me-2"></i>Upload Products
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                        <a href="{{ route('products.import.template') }}" class="btn btn-outline-secondary">
                            <i class="feather-download me-2"></i>Download Template
                        </a>
                    </div>
                    {{-- <button type="submit" class="btn btn-primary">Upload</button> --}}
                </form>
            </div>
        </div>
    </div>
@endsection
