@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Product Edit</h1>
                    @can('add products')
                        <a href="{{ route('products.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Product</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                        <li class="breadcrumb-item active">Product Edit</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body w-50">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <tr>
                    <td>Name:</td>
                    <td>{{ $product->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>SKU:</td>
                    <td>{{ $product->sku ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Category:</td>
                    <td>{{ $product->category->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Brand:</td>
                    <td>{{ $product->brand->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Regular Price:</td>
                    <td>{{ $product->product_meta['regular_price'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Sale Price:</td>
                    <td>{{ $product->product_meta['sale_price'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Weight:</td>
                    <td>{{ $product->product_meta['weight'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Length:</td>
                    <td>{{ $product->product_meta['length'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Width:</td>
                    <td>{{ $product->product_meta['width'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Height:</td>
                    <td>{{ $product->product_meta['height'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Image:</td>
                    <td><img src="{{ asset('uploads') }}/{{ $product->product_image }}" alt=""></td>
                </tr>
            </table>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Rack</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($product->product_stocks as $item)
                        <tr>
                            <td>{{ $item->warehouse->name }}</td>
                            <td>{{ $item->rack->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->barcode }}</td>
                            <td>{{ $item->quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center" colspan="5">No record found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
@endpush
