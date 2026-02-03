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
    <div class="card card-body">
        <form action="{{ route('products.update', $product->id) }}" method="post" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Product Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="sku">SKU: <span class="text-danger">*</span></label>
                    <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="form-control @error('sku') is-invalid @enderror" placeholder="SKU">
                    @error('sku')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="barcode">Barcode: <span class="text-danger">*</span></label>
                    <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" class="form-control @error('barcode') is-invalid @enderror" placeholder="Barcode">
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
                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
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
                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
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
                    <input type="text" id="weight" name="weight" value="{{ old('weight', $product->product_meta['weight']) }}" class="form-control @error('weight') is-invalid @enderror" placeholder="Weight">
                    @error('weight')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="length">Length:</label>
                    <input type="text" id="length" name="length" value="{{ old('length', $product->product_meta['length']) }}" class="form-control @error('length') is-invalid @enderror" placeholder="Length">
                    @error('length')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="width">Width:</label>
                    <input type="text" id="width" name="width" value="{{ old('width', $product->product_meta['width']) }}" class="form-control @error('width') is-invalid @enderror" placeholder="Width">
                    @error('width')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-3 mb-3">
                    <label for="height">Height:</label>
                    <input type="text" id="height" name="height" value="{{ old('height', $product->product_meta['height']) }}" class="form-control @error('height') is-invalid @enderror" placeholder="Height">
                    @error('height')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="regular_price">Regular Price: <span class="text-danger">*</span></label>
                    <input type="text" id="regular_price" name="regular_price" value="{{ old('regular_price', $product->product_meta['regular_price']) }}" class="form-control @error('regular_price') is-invalid @enderror" placeholder="Regular Price">
                    @error('regular_price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="sale_price">Sale Price:</label>
                    <input type="text" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->product_meta['sale_price']) }}" class="form-control @error('sale_price') is-invalid @enderror" placeholder="Sale Price">
                    @error('sale_price')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="stock_quantity">Stock Quantity:</label>
                    <input type="text" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity) }}" class="form-control @error('stock_quantity') is-invalid @enderror" placeholder="Stock Quantity">
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

            <!-- Sales Channels Section -->
            @if(isset($salesChannels) && $salesChannels->count() > 0)
            @php
                $productChannelIds = $product->sales_channels->pluck('id')->toArray();
                $productChannels = $product->sales_channels->keyBy('id');
            @endphp
            <div class="card card-outline card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-store mr-2"></i>Sales Channels</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
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
                                    'active' => '<span class="badge badge-success">Active</span>',
                                    'draft' => '<span class="badge badge-warning">Draft</span>',
                                    'ended' => '<span class="badge badge-secondary">Ended</span>',
                                    'pending' => '<span class="badge badge-info">Pending</span>',
                                    'error' => '<span class="badge badge-danger">Error</span>',
                                    default => '<span class="badge badge-light">Not Listed</span>',
                                };
                            @endphp
                            <div class="col-md-6 mb-3">
                                <div class="card {{ $isListed ? 'border-success' : 'border-secondary' }}">
                                    <div class="card-body p-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="sales_channel_{{ $channel->id }}"
                                                   name="sales_channels[]"
                                                   value="{{ $channel->id }}"
                                                   {{ in_array($channel->id, old('sales_channels', $productChannelIds)) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="sales_channel_{{ $channel->id }}">
                                                <strong>{{ $channel->name }}</strong>
                                            </label>
                                        </div>
                                        <div class="mt-2">
                                            <small>
                                                Status: {!! $statusBadge !!}
                                                @if($channel->hasValidToken())
                                                    <span class="badge badge-success ml-1">Connected</span>
                                                @else
                                                    <span class="badge badge-warning ml-1">Not Connected</span>
                                                @endif
                                            </small>
                                        </div>
                                        @if($externalId)
                                            <div class="mt-1">
                                                <small class="text-muted">Listing ID: {{ $externalId }}</small>
                                            </div>
                                        @endif
                                        @if($listingUrl)
                                            <div class="mt-1">
                                                <a href="{{ $listingUrl }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                                    <i class="fas fa-external-link-alt"></i> View Listing
                                                </a>
                                            </div>
                                        @endif
                                        @if($lastSynced)
                                            <div class="mt-1">
                                                <small class="text-muted">Last synced: {{ \Carbon\Carbon::parse($lastSynced)->diffForHumans() }}</small>
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

            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>

    <!-- Product Stock Section -->
    @if($product->product_stocks && $product->product_stocks->count() > 0)
    <div class="card card-outline card-success mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Product Stock</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('products.update-stock', $product->id) }}" method="post" id="stockUpdateForm">
                @csrf
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
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
                                    <td>{{ $product->sku }}</td>
                                    <td>{{ $product->barcode }}</td>
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
                                <td colspan="4" class="text-right"><strong>Total Stock:</strong></td>
                                <td><strong>{{ $product->product_stocks->sum('quantity') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mt-2">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-sync-alt mr-1"></i> Update Stock & Sync to Sales Channels
                    </button>
                    <small class="text-muted ml-2">This will update stock quantities and sync to all linked sales channels (eBay, etc.)</small>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="card card-outline card-secondary mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-boxes mr-2"></i>Product Stock</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-0">No stock records found for this product. Add stock through purchases.</p>
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
