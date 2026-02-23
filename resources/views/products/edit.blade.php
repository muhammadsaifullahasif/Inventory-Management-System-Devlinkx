@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Product</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
                <li class="breadcrumb-item">Edit Product</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('products.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Products</span>
                    </a>
                    @can('add products')
                    <a href="{{ route('products.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Product</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Product Information -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Product Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('products.update', $product->id) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Product Name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="SKU">
                            @error('sku')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="barcode" class="form-label">Barcode <span class="text-danger">*</span></label>
                            <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" class="form-control @error('barcode') is-invalid @enderror" placeholder="Barcode">
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
                                    <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
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
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
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
                            <input type="text" id="weight" name="weight" value="{{ old('weight', $product->product_meta['weight']) }}" class="form-control @error('weight') is-invalid @enderror" placeholder="Weight">
                            @error('weight')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="length" class="form-label">Length</label>
                            <input type="text" id="length" name="length" value="{{ old('length', $product->product_meta['length']) }}" class="form-control @error('length') is-invalid @enderror" placeholder="Length">
                            @error('length')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="width" class="form-label">Width</label>
                            <input type="text" id="width" name="width" value="{{ old('width', $product->product_meta['width']) }}" class="form-control @error('width') is-invalid @enderror" placeholder="Width">
                            @error('width')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="height" class="form-label">Height</label>
                            <input type="text" id="height" name="height" value="{{ old('height', $product->product_meta['height']) }}" class="form-control @error('height') is-invalid @enderror" placeholder="Height">
                            @error('height')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="regular_price" class="form-label">Regular Price <span class="text-danger">*</span></label>
                            <input type="text" id="regular_price" name="regular_price" value="{{ old('regular_price', $product->product_meta['regular_price']) }}" class="form-control @error('regular_price') is-invalid @enderror" placeholder="Regular Price">
                            @error('regular_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="sale_price" class="form-label">Sale Price</label>
                            <input type="text" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->product_meta['sale_price']) }}" class="form-control @error('sale_price') is-invalid @enderror" placeholder="Sale Price">
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

                    <!-- Sales Channels Section -->
                    @if(isset($salesChannels) && $salesChannels->count() > 0)
                    @php
                        $productChannelIds = $product->sales_channels->pluck('id')->toArray();
                        $productChannels = $product->sales_channels->keyBy('id');
                    @endphp
                    <div class="card mb-4">
                        <div class="card-header bg-soft-info">
                            <h6 class="card-title mb-0"><i class="feather-shopping-bag me-2"></i>Sales Channels</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <strong>Check</strong> to list/activate | <strong>Uncheck</strong> to end/draft listing
                            </p>
                            <div class="row">
                                @foreach($salesChannels as $channel)
                                    @php
                                        $isListed = in_array($channel->id, $productChannelIds);
                                        $channelData = $isListed ? $productChannels->get($channel->id) : null;
                                        $listingStatus = $channelData?->pivot?->listing_status ?? 'not_listed';
                                        $listingUrl = $channelData?->pivot?->listing_url ?? null;
                                        $externalId = $channelData?->pivot?->external_listing_id ?? null;
                                        $lastSynced = $channelData?->pivot?->last_synced_at ?? null;

                                        $statusBadge = match($listingStatus) {
                                            'active' => '<span class="badge bg-soft-success text-success">Active</span>',
                                            'draft' => '<span class="badge bg-soft-warning text-warning">Draft</span>',
                                            'ended' => '<span class="badge bg-soft-secondary text-secondary">Ended</span>',
                                            'pending' => '<span class="badge bg-soft-info text-info">Pending</span>',
                                            'error' => '<span class="badge bg-soft-danger text-danger">Error</span>',
                                            default => '<span class="badge bg-soft-secondary text-secondary">Not Listed</span>',
                                        };
                                    @endphp
                                    <div class="col-md-6 mb-3">
                                        <div class="card {{ $isListed ? 'border-success' : '' }}">
                                            <div class="card-body p-3">
                                                <div class="form-check form-switch">
                                                    <input type="checkbox"
                                                           class="form-check-input"
                                                           id="sales_channel_{{ $channel->id }}"
                                                           name="sales_channels[]"
                                                           value="{{ $channel->id }}"
                                                           {{ in_array($channel->id, old('sales_channels', $productChannelIds)) ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-semibold" for="sales_channel_{{ $channel->id }}">
                                                        {{ $channel->name }}
                                                    </label>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fs-12">
                                                        Status: {!! $statusBadge !!}
                                                        @if($channel->hasValidToken())
                                                            <span class="badge bg-soft-success text-success ms-1">Connected</span>
                                                        @else
                                                            <span class="badge bg-soft-warning text-warning ms-1">Not Connected</span>
                                                        @endif
                                                    </span>
                                                </div>
                                                @if($externalId)
                                                    <div class="mt-1">
                                                        <span class="text-muted fs-11">Listing ID: {{ $externalId }}</span>
                                                    </div>
                                                @endif
                                                @if($listingUrl)
                                                    <div class="mt-2">
                                                        <a href="{{ $listingUrl }}" target="_blank" class="btn btn-sm btn-light-brand">
                                                            <i class="feather-external-link me-1"></i>View Listing
                                                        </a>
                                                    </div>
                                                @endif
                                                @if($lastSynced)
                                                    <div class="mt-1">
                                                        <span class="text-muted fs-11">Last synced: {{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('sales_channels')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Update Product
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Product Stock Section -->
    @if($product->product_stocks && $product->product_stocks->count() > 0)
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-soft-success">
                <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Product Stock</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('products.update-stock', $product->id) }}" method="post" id="stockUpdateForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Rack</th>
                                    <th>SKU</th>
                                    <th>Barcode</th>
                                    <th width="150">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($product->product_stocks as $stock)
                                    <tr>
                                        <td>{{ $stock->warehouse->name ?? 'N/A' }}</td>
                                        <td>{{ $stock->rack->name ?? 'N/A' }}</td>
                                        <td><span class="text-muted fs-12">{{ $product->sku }}</span></td>
                                        <td><span class="text-muted fs-12">{{ $product->barcode }}</span></td>
                                        <td>
                                            <input type="hidden" name="stock_id[]" value="{{ $stock->id }}">
                                            <input type="number" name="quantity[]" value="{{ $stock->quantity }}"
                                                   class="form-control form-control-sm" min="0" style="width: 100px;">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <td colspan="4" class="text-end"><strong>Total Stock:</strong></td>
                                    <td><span class="badge bg-primary fs-12">{{ $product->product_stocks->sum('quantity') }}</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="feather-refresh-cw me-2"></i>Update Stock & Sync to Sales Channels
                        </button>
                        <span class="text-muted ms-2 fs-12">This will update stock quantities and sync to all linked sales channels</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @else
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Product Stock</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0">No stock records found for this product. Add stock through purchases.</p>
            </div>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            $('#warehouse_id').on('change', function(){
                var warehouse_id = $('#warehouse_id').val();

                if (warehouse_id) {
                    $.ajax({
                        url: `{{ route('warehouses.racks', ['warehouse' => ':warehouse_id']) }}`.replace(':warehouse_id', warehouse_id),
                        type: 'GET',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(data) {
                            $('#rack_id').empty();
                            $('#rack_id').append('<option value="">Select Rack</option>');
                            $.each(data, function(key, rack){
                                $('#rack_id').append('<option value="'+ rack.id +'">'+ rack.name +'</option>');
                            });
                        }
                    });
                }
            });
        });
    </script>
@endpush
