@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Product</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Add Product</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('products.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Products</span>
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
            <div class="card-header">
                <h5 class="card-title">Product Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('products.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Product Name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" id="sku" name="sku" value="{{ old('sku') }}" class="form-control @error('sku') is-invalid @enderror" placeholder="SKU">
                            @error('sku')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="barcode" class="form-label">Barcode <span class="text-danger">*</span></label>
                            <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" class="form-control @error('barcode') is-invalid @enderror" placeholder="Barcode">
                            @error('barcode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                <option value="">Select Category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="brand_id" class="form-label">Brand <span class="text-danger">*</span></label>
                            <select name="brand_id" id="brand_id" class="form-select @error('brand_id') is-invalid @enderror">
                                <option value="">Select Brand</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            @error('brand_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <label for="weight" class="form-label">Weight</label>
                            <input type="text" id="weight" name="weight" value="{{ old('weight') }}" class="form-control @error('weight') is-invalid @enderror" placeholder="Weight">
                            @error('weight')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="length" class="form-label">Length</label>
                            <input type="text" id="length" name="length" value="{{ old('length') }}" class="form-control @error('length') is-invalid @enderror" placeholder="Length">
                            @error('length')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="width" class="form-label">Width</label>
                            <input type="text" id="width" name="width" value="{{ old('width') }}" class="form-control @error('width') is-invalid @enderror" placeholder="Width">
                            @error('width')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="height" class="form-label">Height</label>
                            <input type="text" id="height" name="height" value="{{ old('height') }}" class="form-control @error('height') is-invalid @enderror" placeholder="Height">
                            @error('height')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="regular_price" class="form-label">Regular Price <span class="text-danger">*</span></label>
                            <input type="text" id="regular_price" name="regular_price" value="{{ old('regular_price') }}" class="form-control @error('regular_price') is-invalid @enderror" placeholder="Regular Price">
                            @error('regular_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="sale_price" class="form-label">Sale Price</label>
                            <input type="text" id="sale_price" name="sale_price" value="{{ old('sale_price') }}" class="form-control @error('sale_price') is-invalid @enderror" placeholder="Sale Price">
                            @error('sale_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="product_image" class="form-label">Product Image</label>
                        <input type="file" id="product_image" name="product_image" class="form-control @error('product_image') is-invalid @enderror">
                        @error('product_image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Save Product
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
