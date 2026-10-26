@extends('layouts.app')

@section('header')
    
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Print Barcode</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item active">Print Barcode</li>
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
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Product Details</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%;">Name</th>
                            <td>{{ $product->name }}</td>
                        </tr>
                        <tr>
                            <th>SKU</th>
                            <td>{{ $product->sku }}</td>
                        </tr>
                        <tr>
                            <th>Barcode</th>
                            <td>{{ $product->barcode ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Print Settings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('products.barcode.print', $product->id) }}" id="printForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="quantity">Number of Barcodes:</label>
                                    <input type="number" id="quantity" name="quantity" class="form-control" value="21" min="1" max="100">
                                    <small class="text-muted">1-100 labels</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="columns">Columns per Row:</label>
                                    <select id="columns" name="columns" class="form-control">
                                        <option value="2">2 Columns</option>
                                        <option value="3" selected>3 Columns</option>
                                        <option value="4">4 Columns</option>
                                        <option value="5">5 Columns</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary mb-3">
                                <i class="fas fa-print me-2"></i>Print Barcodes
                            </button>
                            <a href="{{ route('products.index') }}" class="btn btn-secondary mb-3">
                                <i class="fas fa-arrow-left me-2"></i>Back to Products
                            </a>
                            <a href="{{ route('products.barcode.bulk-form') }}" class="btn btn-info mb-3">
                                <i class="fas fa-layer-group me-2"></i>Bulk Print
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Barcode Preview -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Barcode Preview</h3>
        </div>
        <div class="card-body text-center">
            @php
                $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode ?? $product->sku);
                $renderer = new Picqer\Barcode\Renderers\PngRenderer();
                $renderer->setForegroundColor([0, 0, 0]);
                $pngData = $renderer->render($barcode, 200, 60);
                $base64 = base64_encode($pngData);
            @endphp
            <div style="display: inline-block; border: 1px solid #000; padding: 15px;">
                <img src="data:image/png;base64,{{ $base64 }}" style="max-width: 200px;" />
                <div style="margin-top: 5px; font-size: 14px;">{{ $product->barcode ?? $product->sku }}</div>
                <div style="margin-top: 2px; font-size: 10px; color: #666;">{{ $product->name }}</div>
            </div>
        </div>
    </div>
@endsection
