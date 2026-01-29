@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Print Barcode</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                        <li class="breadcrumb-item active">Print Barcode</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
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
                        <div class="form-group">
                            <label for="quantity">Number of Barcodes:</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" value="21" min="1" max="100">
                            <small class="text-muted">Enter the number of barcode labels to print (1-100)</small>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Barcodes
                            </button>
                            <a href="{{ route('products.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Products
                            </a>
                            <a href="{{ route('products.barcode.bulk-form') }}" class="btn btn-info">
                                <i class="fas fa-layer-group"></i> Bulk Print
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
