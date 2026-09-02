@extends('layouts.app')

@push('styles')
<link rel="stylesheet" type="text/css" href="{{ asset('vendors/css/select2.min.css') }}" />
<link rel="stylesheet" type="text/css" href="{{ asset('vendors/css/select2-theme.min.css') }}" />
<link rel="stylesheet" type="text/css" href="{{ asset('vendors/css/quill.min.css') }}" />
@endpush

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

                    <!-- Tabs -->
                    <ul class="nav nav-tabs nav-pills mb-4" id="productTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="product-details-tab" data-bs-toggle="tab" data-bs-target="#product-details" type="button" role="tab">
                                <i class="feather-info me-1"></i>Product Details
                            </button>
                        </li>
                        @foreach($salesChannels ?? [] as $channel)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="channel-{{ $channel->id }}-tab" data-bs-toggle="tab" data-bs-target="#channel-{{ $channel->id }}" type="button" role="tab">
                                    <i class="feather-shopping-bag me-1"></i>{{ $channel->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="productTabContent">
                    <div class="tab-pane fade show active" id="product-details" role="tabpanel">

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Product Name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                            <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="SKU (also used as Barcode)">
                            @error('sku')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-4">
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
                        <div class="col-md-4 mb-4">
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
                        <div class="col-md-4 mb-4">
                            <label for="is_bundle" class="form-label">Product Type</label>
                            <div class="form-check form-switch" style="padding-top: 8px;">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_bundle" name="is_bundle" value="1" {{ old('is_bundle', $product->is_bundle) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_bundle">This is a Bundle Product</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <label for="weight" class="form-label">Weight</label>
                            <input type="text" id="weight" name="weight" value="{{ old('weight', $product->product_meta['weight'] ?? '') }}" class="form-control @error('weight') is-invalid @enderror" placeholder="Weight">
                            @error('weight')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="length" class="form-label">Length</label>
                            <input type="text" id="length" name="length" value="{{ old('length', $product->product_meta['length'] ?? '') }}" class="form-control @error('length') is-invalid @enderror" placeholder="Length">
                            @error('length')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="width" class="form-label">Width</label>
                            <input type="text" id="width" name="width" value="{{ old('width', $product->product_meta['width'] ?? '') }}" class="form-control @error('width') is-invalid @enderror" placeholder="Width">
                            @error('width')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3 mb-4">
                            <label for="height" class="form-label">Height</label>
                            <input type="text" id="height" name="height" value="{{ old('height', $product->product_meta['height'] ?? '') }}" class="form-control @error('height') is-invalid @enderror" placeholder="Height">
                            @error('height')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="regular_price" class="form-label">Regular Price <span class="text-danger">*</span></label>
                            <input type="text" id="regular_price" name="regular_price" value="{{ old('regular_price', $product->product_meta['regular_price'] ?? '') }}" class="form-control @error('regular_price') is-invalid @enderror" placeholder="Regular Price">
                            @error('regular_price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="sale_price" class="form-label">Sale Price</label>
                            <input type="text" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->product_meta['sale_price'] ?? '') }}" class="form-control @error('sale_price') is-invalid @enderror" placeholder="Sale Price">
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

                    <div class="mb-4">
                        <label for="tagsInput" class="form-label">Tags</label>
                        <div id="tagsContainer" class="form-control d-flex flex-wrap align-items-center gap-2" style="min-height: 44px; height: auto; cursor: text;">
                            <input type="text" id="tagsInput" class="border-0 flex-grow-1 p-0" style="outline: none; min-width: 120px;" placeholder="Type a tag and press comma or Enter">
                        </div>
                        <input type="hidden" name="tags" id="tags" value="{{ old('tags', $product->product_meta['tags'] ?? '') }}">
                        <small class="text-muted">Press comma or Enter after each keyword to create a tag.</small>
                        @error('tags')
                            <div class="text-danger fs-12">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Bundle Components Section -->
                    <div id="bundleComponentsSection" style="display: {{ old('is_bundle', $product->is_bundle) ? 'block' : 'none' }};">
                        <div class="card bg-light border mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                                <h6 class="mb-0">Bundle Components <span class="text-danger">*</span></h6>
                                <button type="button" class="btn btn-sm btn-primary" id="addComponentBtn">
                                    <i class="feather-plus me-1"></i> Add Component
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3">
                                    <i class="feather-info me-2"></i>
                                    <strong>Auto-Pairing:</strong> Each bundle uses 1 of each component. Bundle quantity is automatically calculated based on the product with the lowest stock.
                                </div>
                                <div id="componentsContainer"></div>
                                @error('components')
                                    <div class="text-danger mt-2">{{ $message }}</div>
                                @enderror

                                <!-- Stock Preview -->
                                <div class="mt-3 p-3 bg-white rounded border">
                                    <h6 class="mb-2">Stock Preview</h6>
                                    <div id="stockPreview" class="text-center text-muted py-2">
                                        <small>Loading stock calculation...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>
                    <!-- /Product Details tab-pane -->

                    <!-- Sales Channel Tabs -->
                    @if(isset($salesChannels) && $salesChannels->count() > 0)
                    @php
                        $productChannelIds = $product->sales_channels->pluck('id')->toArray();
                        $productChannels = $product->sales_channels->keyBy('id');
                    @endphp
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
                        <div class="tab-pane fade" id="channel-{{ $channel->id }}" role="tabpanel">

                            <div class="row mb-4">
                                @if($externalId)
                                <div class="col-md-12 mb-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary fetch-ebay-listing-btn"
                                            data-channel-id="{{ $channel->id }}"
                                            data-url="{{ route('ebay.item.details', [$channel->id, $externalId]) }}">
                                        <i class="feather-download-cloud me-1"></i><span class="btn-text">Fetch Title/Description/Price from eBay</span>
                                    </button>
                                </div>
                                @endif
                                <div class="col-md-12 mb-3">
                                    <label for="channel_{{ $channel->id }}_title" class="form-label">Listing Title <small class="text-muted">(max 80 chars, defaults to product name)</small></label>
                                    <input type="text" maxlength="80"
                                           id="channel_{{ $channel->id }}_title"
                                           name="channel_data[{{ $channel->id }}][title]"
                                           value="{{ old('channel_data.' . $channel->id . '.title', $channelData?->pivot?->title) }}"
                                           class="form-control @error('channel_data.' . $channel->id . '.title') is-invalid @enderror"
                                           placeholder="{{ $product->name }}">
                                    @error('channel_data.' . $channel->id . '.title')
                                        <span class="text-danger fs-12">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Listing Description <small class="text-muted">(defaults to product description)</small></label>
                                    <div id="channel_{{ $channel->id }}_description_editor" class="channel-description-editor" data-channel-id="{{ $channel->id }}" style="height: 180px; background: #fff;"></div>
                                    <textarea name="channel_data[{{ $channel->id }}][description]"
                                              id="channel_{{ $channel->id }}_description"
                                              class="d-none">{{ old('channel_data.' . $channel->id . '.description', $channelData?->pivot?->description) }}</textarea>
                                    @error('channel_data.' . $channel->id . '.description')
                                        <span class="text-danger fs-12">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="channel_{{ $channel->id }}_regular_price" class="form-label">Regular Price <small class="text-muted">(defaults to product price)</small></label>
                                    <input type="number" step="0.01" min="0"
                                           id="channel_{{ $channel->id }}_regular_price"
                                           name="channel_data[{{ $channel->id }}][regular_price]"
                                           value="{{ old('channel_data.' . $channel->id . '.regular_price', $channelData?->pivot?->regular_price) }}"
                                           class="form-control @error('channel_data.' . $channel->id . '.regular_price') is-invalid @enderror"
                                           placeholder="{{ number_format($product->price, 2) }}">
                                    @error('channel_data.' . $channel->id . '.regular_price')
                                        <span class="text-danger fs-12">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="channel_{{ $channel->id }}_sale_price" class="form-label">Sale Price</label>
                                    <input type="number" step="0.01" min="0"
                                           id="channel_{{ $channel->id }}_sale_price"
                                           name="channel_data[{{ $channel->id }}][sale_price]"
                                           value="{{ old('channel_data.' . $channel->id . '.sale_price', $channelData?->pivot?->sale_price) }}"
                                           class="form-control @error('channel_data.' . $channel->id . '.sale_price') is-invalid @enderror"
                                           placeholder="Optional">
                                    @error('channel_data.' . $channel->id . '.sale_price')
                                        <span class="text-danger fs-12">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <p class="text-muted mb-3">
                                <strong>Check</strong> to list/activate | <strong>Uncheck</strong> to end/draft listing
                            </p>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <tbody>
                                        <tr>
                                            <th style="width: 220px;">Channel Name</th>
                                            <td>{{ $channel->name }}</td>
                                        </tr>
                                        <tr>
                                            <th>List / Activate</th>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input type="checkbox"
                                                           class="form-check-input sales-channel-checkbox"
                                                           id="sales_channel_{{ $channel->id }}"
                                                           name="sales_channels[]"
                                                           value="{{ $channel->id }}"
                                                           data-channel-id="{{ $channel->id }}"
                                                           @if($externalId) data-fetch-url="{{ route('ebay.item.details', [$channel->id, $externalId]) }}" @endif
                                                           {{ in_array($channel->id, old('sales_channels', $productChannelIds)) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="sales_channel_{{ $channel->id }}">
                                                        {{ $isListed ? 'Listed' : 'Not Listed' }}
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Connection Status</th>
                                            <td>
                                                @if($channel->hasValidToken())
                                                    <span class="badge bg-soft-success text-success">Connected</span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning">Not Connected</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Listing Status</th>
                                            <td>{!! $statusBadge !!}</td>
                                        </tr>
                                        @if($externalId)
                                        <tr>
                                            <th>Listing ID</th>
                                            <td>{{ $externalId }}</td>
                                        </tr>
                                        @endif
                                        @if($listingUrl)
                                        <tr>
                                            <th>Listing URL</th>
                                            <td>
                                                <a href="{{ $listingUrl }}" target="_blank" class="btn btn-sm btn-light-brand">
                                                    <i class="feather-external-link me-1"></i>View Listing
                                                </a>
                                            </td>
                                        </tr>
                                        @endif
                                        @if($lastSynced)
                                        <tr>
                                            <th>Last Synced</th>
                                            <td>{{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }}</td>
                                        </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                            @error('sales_channels')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                    @endif

                    </div>
                    <!-- /tab-content -->

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Update Product
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Product Stock Section (Only for non-bundle products) -->
    @if(!$product->is_bundle)
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
                                        <th style="width: 250px;">Rack</th>
                                        <th>SKU</th>
                                        <th>Barcode</th>
                                        <th width="150">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($product->product_stocks as $stock)
                                        <tr>
                                            <td>{{ $stock->warehouse->name ?? 'N/A' }}</td>
                                            {{-- <td>{{ $stock->rack->name ?? 'N/A' }}</td> --}}
                                            <td>
                                                <select name="rack[]" class="form-select form-control-sm" style="width: 200px;">
                                                    <option value="">Select Rack</option>
                                                    @foreach ($stock->warehouse->racks as $rack)
                                                        <option value="{{ $rack->id }}" {{ ($stock->rack_id === $rack->id) ? 'selected' : '' }}>{{ $rack->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
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
    @else
        <!-- Bundle Stock Information -->
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-soft-info">
                    <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Bundle Stock Information</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="feather-info me-2"></i>
                        This is a bundle product. Stock is automatically calculated based on component availability. Update component stock to change bundle stock.
                    </div>

                    @if($product->bundleComponents->count() > 0)
                        <div class="d-flex align-items-center justify-content-between mb-4 p-3 bg-light rounded">
                            <div>
                                <h6 class="mb-1">Available Bundles</h6>
                                <small class="text-muted">Based on component stock</small>
                            </div>
                            <h3 class="mb-0">
                                @php $bundleStock = $product->available_stock; @endphp
                                <span class="badge bg-{{ $bundleStock > 10 ? 'success' : ($bundleStock > 0 ? 'warning' : 'danger') }} fs-5">
                                    {{ $bundleStock }}
                                </span>
                            </h3>
                        </div>

                        <h6 class="mb-3">Bundle Components</h6>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th class="text-center">Required Qty</th>
                                        <th class="text-center">Available Stock</th>
                                        <th class="text-center">Possible Bundles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $bundleDetails = $product->getBundleStockDetails(); @endphp
                                    @foreach($bundleDetails['components'] as $component)
                                        <tr class="{{ $component['product_name'] == $bundleDetails['limiting_component'] ? 'table-warning' : '' }}">
                                            <td>
                                                {{ $component['product_name'] }}
                                                @if($component['product_name'] == $bundleDetails['limiting_component'])
                                                    <span class="badge bg-warning text-dark ms-2">Limiting</span>
                                                @endif
                                            </td>
                                            <td><span class="text-muted fs-12">{{ $component['product_sku'] }}</span></td>
                                            <td class="text-center">{{ $component['required_qty'] }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-info text-info">{{ $component['available_stock'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-secondary text-secondary">{{ $component['possible_bundles'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No components configured for this bundle.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('vendors/js/quill.min.js') }}"></script>
    <script>
        $(document).ready(function(){
            let componentIndex = 0;

            // Per sales-channel listing description editors (Quill), synced to hidden textarea on submit
            (function() {
                var editors = [];
                var editorsByChannel = {};
                const toolbarOptions = [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'font': [] }],

                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'color': [] }, { 'background': [] }],

                    ['link', 'formula'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }, { 'list': 'check' }],
                    [{ 'script': 'sub' }, { 'script': 'super' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    [{ 'direction': 'rtl' }],

                    [{ 'align': [] }],

                    ['clean']
                ];
                $('.channel-description-editor').each(function() {
                    var $editorDiv = $(this);
                    var channelId = $editorDiv.data('channel-id');
                    var $textarea = $editorDiv.next('textarea[id$="_description"]');
                    if (!$textarea.length) return;

                    var quill = new Quill($editorDiv[0], {
                        theme: 'snow',
                        placeholder: 'Optional — overrides product description for this channel',
                        modules: {
                            toolbar: toolbarOptions
                        }
                    });
                    quill.root.innerHTML = $textarea.val() || '';

                    editors.push({ quill: quill, textarea: $textarea });
                    editorsByChannel[channelId] = quill;
                });

                $('form').on('submit', function() {
                    editors.forEach(function(entry) {
                        var html = entry.quill.getText().trim() === '' ? '' : entry.quill.root.innerHTML;
                        entry.textarea.val(html);
                    });
                });

                // Pull title/description/price from the live eBay listing into this channel's fields
                $('.fetch-ebay-listing-btn').on('click', function() {
                    var $btn = $(this);
                    var channelId = $btn.data('channel-id');
                    var $btnText = $btn.find('.btn-text');
                    var originalText = $btnText.text();

                    $btn.prop('disabled', true);
                    $btnText.text('Fetching...');

                    $.get($btn.data('url'))
                        .done(function(resp) {
                            if (!resp || !resp.success || !resp.item) {
                                alert('Could not fetch the eBay listing.');
                                return;
                            }

                            var item = resp.item;

                            if (item.title) {
                                $('#channel_' + channelId + '_title').val(item.title);
                            }

                            if (editorsByChannel[channelId] && typeof item.description === 'string') {
                                editorsByChannel[channelId].root.innerHTML = item.description;
                            }

                            if (item.regular_price && item.regular_price.value) {
                                $('#channel_' + channelId + '_regular_price').val(item.regular_price.value);
                            }

                            $('#channel_' + channelId + '_sale_price').val(
                                (item.sale_price && item.sale_price.value) ? item.sale_price.value : ''
                            );
                        })
                        .fail(function() {
                            alert('Failed to fetch listing from eBay.');
                        })
                        .always(function() {
                            $btn.prop('disabled', false);
                            $btnText.text(originalText);
                        });
                });

                // Auto-fill regular/sale price from the live eBay listing when a channel is
                // checked (or already checked on load) and the price fields are still empty.
                // Only runs for channels already connected to an eBay listing (data-fetch-url set).
                function autoFillChannelPrice(channelId, fetchUrl) {
                    if (!fetchUrl) return;

                    var $regular = $('#channel_' + channelId + '_regular_price');
                    var $sale = $('#channel_' + channelId + '_sale_price');
                    if ($regular.val() || $sale.val()) return; // already has a price, don't overwrite

                    $.get(fetchUrl).done(function(resp) {
                        if (!resp || !resp.success || !resp.item) return;
                        var item = resp.item;
                        if (item.regular_price && item.regular_price.value) {
                            $regular.val(item.regular_price.value);
                        }
                        if (item.sale_price && item.sale_price.value) {
                            $sale.val(item.sale_price.value);
                        }
                    });
                }

                $('.sales-channel-checkbox').on('change', function() {
                    var $cb = $(this);
                    if ($cb.is(':checked')) {
                        autoFillChannelPrice($cb.data('channel-id'), $cb.data('fetch-url'));
                    }
                });

                // Also run once for channels already checked/listed on page load
                $('.sales-channel-checkbox:checked').each(function() {
                    var $cb = $(this);
                    autoFillChannelPrice($cb.data('channel-id'), $cb.data('fetch-url'));
                });
            })();

            // Tags input (pill style)
            (function() {
                const $hidden = $('#tags');
                let tags = ($hidden.val() || '').split(',').map(t => t.trim()).filter(Boolean);
                const $container = $('#tagsContainer');
                const $input = $('#tagsInput');

                function render() {
                    $container.find('.tag-pill').remove();
                    tags.forEach(function(tag, index) {
                        $('<span class="badge bg-soft-primary text-primary tag-pill d-flex align-items-center gap-1 py-2 px-2"></span>')
                            .text(tag)
                            .append($('<i class="feather-x tag-remove ms-1" style="cursor:pointer;"></i>').data('index', index))
                            .insertBefore($input);
                    });
                    $hidden.val(tags.join(','));
                }

                function addTag(value) {
                    value = value.trim().replace(/,+$/, '');
                    if (value && !tags.includes(value)) {
                        tags.push(value);
                        render();
                    }
                }

                $container.on('click', function(e) {
                    if (e.target === this) $input.trigger('focus');
                });

                $input.on('keydown', function(e) {
                    if (e.key === ',' || e.key === 'Enter') {
                        e.preventDefault();
                        addTag($input.val());
                        $input.val('');
                    } else if (e.key === 'Backspace' && $input.val() === '' && tags.length) {
                        tags.pop();
                        render();
                    }
                });

                $input.on('blur', function() {
                    if ($input.val().trim()) {
                        addTag($input.val());
                        $input.val('');
                    }
                });

                $container.on('click', '.tag-remove', function() {
                    tags.splice($(this).data('index'), 1);
                    render();
                });

                render();
            })();

            // Existing components from database
            const existingComponents = @json($product->bundleComponents->map(function($component) {
                return [
                    'product_id' => $component->component_product_id,
                    'quantity' => $component->quantity_required
                ];
            })->values());

            // Toggle bundle sections
            $('#is_bundle').change(function() {
                if($(this).is(':checked')) {
                    $('#bundleComponentsSection').slideDown();
                } else {
                    $('#bundleComponentsSection').slideUp();
                    $('#componentsContainer').empty();
                    componentIndex = 0;
                }
            });

            // Add component row
            $('#addComponentBtn').click(function() {
                addComponentRow();
            });

            function addComponentRow(productId = '', quantity = 1) {
                const row = `
                    <div class="component-row mb-3 p-3 border rounded bg-white" data-index="${componentIndex}">
                        <div class="row g-3">
                            <div class="col-md-11">
                                <label class="form-label">Product</label>
                                <select name="components[${componentIndex}][product_id]" class="form-select component-product" required>
                                    <option value="">Select Product</option>
                                    @foreach($products ?? [] as $product)
                                        <option value="{{ $product->id }}" data-name="{{ $product->name }}"
                                            ${productId == '{{ $product->id }}' ? 'selected' : ''}>
                                            {{ $product->name }} ({{ $product->sku }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <input type="hidden" name="components[${componentIndex}][quantity]" class="component-quantity" value="1">
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-light-danger remove-component-btn">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#componentsContainer').append(row);

                // Initialize Select2 on the newly added select
                const $newRow = $('.component-row').last();
                $newRow.find('.component-product').select2({
                    placeholder: 'Search and select a product...',
                    allowClear: true,
                    width: '100%',
                    theme: 'bootstrap-5'
                });

                componentIndex++;
                updateStockPreview();
            }

            // Remove component
            $(document).on('click', '.remove-component-btn', function() {
                $(this).closest('.component-row').remove();
                updateStockPreview();
            });

            // Update stock preview when product is selected
            $(document).on('change', '.component-product', function() {
                updateStockPreview();
            });

            function updateStockPreview() {
                const components = [];
                let isValid = true;

                $('.component-row').each(function() {
                    const productId = $(this).find('.component-product').val();
                    const quantity = parseInt($(this).find('.component-quantity').val());

                    if (productId && quantity > 0) {
                        components.push({
                            product_id: productId,
                            quantity: quantity
                        });
                    } else {
                        isValid = false;
                    }
                });

                if (!isValid || components.length < 2) {
                    $('#stockPreview').html(`<small class="text-muted">Add at least 2 components to see stock calculation</small>`);
                    return;
                }

                // Calculate stock preview
                $.ajax({
                    url: '{{ route('bundles.calculate-stock') }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        components: components
                    },
                    success: function(response) {
                        if (response.success) {
                            let html = `
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Available Bundles:</strong>
                                    <span class="badge bg-${response.available_bundles > 10 ? 'success' : response.available_bundles > 0 ? 'warning' : 'danger'} fs-6">
                                        ${response.available_bundles}
                                    </span>
                                </div>
                            `;

                            if (response.limiting_component) {
                                html += `<small class="text-muted">Limited by: <strong>${response.limiting_component}</strong></small>`;
                            }

                            $('#stockPreview').html(html);
                        }
                    },
                    error: function() {
                        $('#stockPreview').html(`<small class="text-danger">Failed to calculate stock</small>`);
                    }
                });
            }

            // Load existing components
            existingComponents.forEach(function(component) {
                addComponentRow(component.product_id, component.quantity);
            });

            // If no components exist and bundle is enabled, add two empty ones
            if (existingComponents.length === 0 && $('#is_bundle').is(':checked')) {
                addComponentRow();
                addComponentRow();
            }

            // Warehouse/Rack AJAX
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
