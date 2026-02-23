@extends('layouts.app')

{{-- @section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Product Import</h1>
                    @can('add products')
                        <a href="{{ route('products.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Product</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                        <li class="breadcrumb-item active">Product Import</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection --}}

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Product Import Preview</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.import') }}">Product Import</a></li>
                <li class="breadcrumb-item">Product Import Preview</li>
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

@push('styles')
    <style>
        .table thead tr th, .table tbody tr td, .table tfoot tr th {
            padding: .25rem 0.125rem !important;
        }
    </style>
@endpush

@section('content')
    <form action="{{ route('products.import.store') }}" class="card" method="POST">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $warehouse }}">
        <div class="card-header px-2">
            <h5 class="card-title">Products Import Preview</h5>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th style="width: 15rem;">Name</th>
                            <th style="width: 15rem;">Description</th>
                            <th style="width: 8rem;">Category</th>
                            <th style="width: 8rem;">Brand</th>
                            <th style="width: 8rem;">SKU</th>
                            <th style="width: 8rem;">Barcode</th>
                            <th style="width: 5rem;">Regular Price</th>
                            <th style="width: 5rem;">Sale Price</th>
                            <th style="width: 5rem;">Qty</th>
                            <th style="width: 5rem;">Weight</th>
                            <th style="width: 5rem;">Length</th>
                            <th style="width: 5rem;">Width</th>
                            <th style="width: 5rem;">Height</th>
                            <th style="width: 8rem;">Rack</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <input type="text" name="products[name][]" value="{{ $product['name'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <textarea name="products[description][]" rows="3" class="form-control form-control-sm" required>{{ $product['description'] }}</textarea>
                                </td>
                                <td>
                                    <select name="products[category][]" class="form-select form-control-sm" required>
                                        <option value="">Select Category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @if($category->id === $product['category_id']) selected @endif>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="products[brand][]" class="form-select form-control-sm" required>
                                        <option value="">Select Brand</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}" @if($brand->id === $product['brand_id']) selected @endif>{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="products[sku][]" value="{{ $product['sku'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="text" name="products[barcode][]" value="{{ $product['barcode'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="text" name="products[regular_price][]" value="{{ $product['regular_price'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="text" name="products[sale_price][]" value="{{ $product['sale_price'] }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="products[quantity][]" value="{{ $product['quantity'] }}" class="form-control form-control-sm" required>
                                </td>
                                <td>
                                    <input type="number" name="products[weight][]" value="{{ $product['weight'] }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="products[length][]" value="{{ $product['length'] }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="products[width][]" value="{{ $product['width'] }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="products[height]" value="{{ $product['height'] }}" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <select name="products[rack][]" class="form-select form-control-sm" required>
                                        <option value="">Select Rack</option>
                                        @foreach ($racks as $rack)
                                            <option value="{{ $rack->id }}" @if($rack->is_default) selected @endif>{{ $rack->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center">No Product Found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer px-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="feather-upload me-2"></i>Upload Products
                </button>
                <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
            </div>
        </div>
    </form>
@endsection
