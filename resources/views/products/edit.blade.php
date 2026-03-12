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

                    <div id="bundleTypeContainer" class="mb-4" style="display: {{ old('is_bundle', $product->is_bundle) ? 'block' : 'none' }};">
                        <label for="bundle_type" class="form-label">Bundle Type</label>
                        <select name="bundle_type" id="bundle_type" class="form-select @error('bundle_type') is-invalid @enderror">
                            <option value="pair" {{ old('bundle_type', $product->bundle_type) == 'pair' ? 'selected' : '' }}>Pair</option>
                            <option value="kit" {{ old('bundle_type', $product->bundle_type) == 'kit' ? 'selected' : '' }}>Kit</option>
                            <option value="set" {{ old('bundle_type', $product->bundle_type) == 'set' ? 'selected' : '' }}>Set</option>
                            <option value="bundle" {{ old('bundle_type', $product->bundle_type) == 'bundle' ? 'selected' : '' }}>Bundle</option>
                        </select>
                        @error('bundle_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
    <script>
        $(document).ready(function(){
            let componentIndex = 0;

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
                    $('#bundleTypeContainer').slideDown();
                    $('#bundleComponentsSection').slideDown();
                } else {
                    $('#bundleTypeContainer').slideUp();
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
