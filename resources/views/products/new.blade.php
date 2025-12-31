@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Product New</h1>
                    @can('add products')
                        <a href="{{ route('products.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Product</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                        <li class="breadcrumb-item active">Product New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body w-50">
        <form action="{{ route('products.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Product Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="sku">SKU: <span class="text-danger">*</span></label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku') }}" class="form-control @error('sku') is-invalid @enderror" placeholder="SKU">
                    @error('sku')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="barcode">Barcode: <span class="text-danger">*</span></label>
                    <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" class="form-control @error('barcode') is-invalid @enderror" placeholder="Barcode">
                    @error('barcode')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="category_id">Category: <span class="text-danger">*</span></label>
                    <select name="category_id" id="category_id" class="form-control @error('category_id') is-invalid @enderror">
                        <option value="">Select Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="brand_id">Brand: <span class="text-danger">*</span></label>
                    <select name="brand_id" id="brand_id" class="form-control @error('brand_id') is-invalid @enderror">
                        <option value="">Select Brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="weight">Weight:</label>
                    <input type="text" id="weight" name="weight" value="{{ old('weight') }}" class="form-control @error('weight') is-invalid @enderror" placeholder="Weight">
                    @error('weight')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="length">Length:</label>
                    <input type="text" id="length" name="length" value="{{ old('length') }}" class="form-control @error('length') is-invalid @enderror" placeholder="Length">
                    @error('length')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="width">Width:</label>
                    <input type="text" id="width" name="width" value="{{ old('width') }}" class="form-control @error('width') is-invalid @enderror" placeholder="Width">
                    @error('width')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="height">Height:</label>
                    <input type="text" id="height" name="height" value="{{ old('height') }}" class="form-control @error('height') is-invalid @enderror" placeholder="Height">
                    @error('height')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="regular_price">Regular Price: <span class="text-danger">*</span></label>
                    <input type="text" id="regular_price" name="regular_price" value="{{ old('regular_price') }}" class="form-control @error('regular_price') is-invalid @enderror" placeholder="Regular Price">
                    @error('regular_price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="sale_price">Sale Price:</label>
                    <input type="text" id="sale_price" name="sale_price" value="{{ old('sale_price') }}" class="form-control @error('sale_price') is-invalid @enderror" placeholder="Sale Price">
                    @error('sale_price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="stock_quantity">Stock Quantity:</label>
                    <input type="text" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', '0') }}" class="form-control @error('stock_quantity') is-invalid @enderror" placeholder="Stock Quantity">
                    @error('stock_quantity')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="product_image" class="d-block">Product Image:</label>
                    <input type="file" id="product_image" name="product_image" class="@error('product_image') is-invalid @enderror">
                    @error('product_image')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
