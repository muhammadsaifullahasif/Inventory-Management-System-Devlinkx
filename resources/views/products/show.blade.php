@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Product Details</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">View Product</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('products.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Products</span>
                    </a>
                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary">
                        <i class="feather-edit me-2"></i>
                        <span>Edit Product</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-xxl-8">
        <!-- Product Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Product Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="150">Name:</td>
                                <td class="fw-semibold">{{ $product->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">SKU:</td>
                                <td><span class="badge bg-soft-primary text-primary">{{ $product->sku ?? 'N/A' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Barcode:</td>
                                <td><span class="badge bg-soft-secondary text-secondary">{{ $product->barcode ?? 'N/A' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Category:</td>
                                <td>{{ $product->category->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Brand:</td>
                                <td>{{ $product->brand->name ?? 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="150">Regular Price:</td>
                                <td class="fw-semibold text-success">${{ number_format($product->product_meta['regular_price'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Sale Price:</td>
                                <td class="fw-semibold text-danger">${{ number_format($product->product_meta['sale_price'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Weight:</td>
                                <td>{{ $product->product_meta['weight'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Dimensions:</td>
                                <td>{{ $product->product_meta['length'] ?? '-' }} x {{ $product->product_meta['width'] ?? '-' }} x {{ $product->product_meta['height'] ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-package me-2"></i>Stock Information</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th>Rack</th>
                                <th>SKU</th>
                                <th>Barcode</th>
                                <th class="text-center">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($product->product_stocks as $item)
                                <tr>
                                    <td>{{ $item->warehouse->name }}</td>
                                    <td>{{ $item->rack->name }}</td>
                                    <td><span class="text-muted fs-12">{{ $product->sku }}</span></td>
                                    <td><span class="text-muted fs-12">{{ $product->barcode }}</span></td>
                                    <td class="text-center">
                                        @if($item->quantity > 0)
                                            <span class="badge bg-soft-success text-success">{{ $item->quantity }}</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger">Out of Stock</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-muted py-4" colspan="5">No stock records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($product->product_stocks->count() > 0)
                        <tfoot>
                            <tr class="bg-light">
                                <td colspan="4" class="text-end"><strong>Total Stock:</strong></td>
                                <td class="text-center"><span class="badge bg-primary">{{ $product->product_stocks->sum('quantity') }}</span></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <!-- Product Image -->
        @if($product->product_image)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Product Image</h5>
            </div>
            <div class="card-body text-center">
                <img src="{{ $product->getImageUrl() }}" alt="{{ $product->name }}" class="img-fluid rounded" style="max-height: 300px;">
            </div>
        </div>
        @endif

        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted">Total Stock</span>
                    <span class="badge bg-soft-primary text-primary fs-12">{{ $product->product_stocks->sum('quantity') }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted">Warehouses</span>
                    <span class="badge bg-soft-info text-info fs-12">{{ $product->product_stocks->unique('warehouse_id')->count() }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted">Sales Channels</span>
                    <span class="badge bg-soft-success text-success fs-12">{{ $product->sales_channels->count() }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted">Created</span>
                    <span class="fs-12">{{ $product->created_at->format('M d, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
@endsection
