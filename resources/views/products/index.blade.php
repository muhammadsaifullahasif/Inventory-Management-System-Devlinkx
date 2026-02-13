@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Products</h1>
                    @can('add products')
                        <a href="{{ route('products.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Product</a>
                    @endcan
                    <a href="{{ route('products.import') }}" class="btn btn-outline-success btn-sm mb-3">Products Import</a>
                    <a href="{{ route('products.barcode.bulk-form') }}" class="btn btn-outline-secondary btn-sm mb-3">Print Barcodes</a>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Products</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="card card-outline card-primary mb-3">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body py-2">
            <form action="{{ route('products.index') }}" method="GET" id="filterForm">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label class="small mb-1">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SKU, Barcode..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Category</label>
                        <select name="category_id" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Brand</label>
                        <select name="brand_id" class="form-control form-control-sm">
                            <option value="">All Brands</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Stock Status</label>
                        <select name="stock_status" class="form-control form-control-sm">
                            <option value="">All</option>
                            <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                            <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Sales Channel</label>
                        <select name="sales_channel_id" class="form-control form-control-sm">
                            <option value="">All Channels</option>
                            @foreach($salesChannels as $channel)
                                <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Date From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Date To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-4 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times mr-1"></i>Clear</a>
                    </div>
                    <div class="col-md-4 mb-2 d-flex align-items-end justify-content-end">
                        <span class="text-muted small">Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Barcode</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Category</th>
                    <th>Sales Channels</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>{{ $product->id }}</td>
                        <td style="width: 100px;">
                            <div style="max-width: 100px;">
                                @php
                                    // Make Barcode object of Code128 encoding.
                                    $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
                                    $renderer = new Picqer\Barcode\Renderers\SvgRenderer();
                                    // $renderer->setSvgType($renderer::TYPE_SVG_INLINE); // Changes the output to be used inline inside HTML documents, instead of a standalone SVG image (default)
                                    $renderer->setSvgType($renderer::TYPE_SVG_STANDALONE); // If you want to force the default, create a stand alone SVG image
                                    // echo $renderer->render($barcode, 80, 40);
                                @endphp
                                {!! $renderer->render($barcode, 100, 40) !!}
                                <br>
                                {{ $product->barcode }}<br>
                                <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank">Print Barcode</a>
                            </div>
                        </td>
                        <td>
                            {{ $product->name }}
                        </td>
                        <td>{{ $product->price }}</td>
                        <td>{{ $product->product_stocks->sum('quantity'); }}</td>
                        <td>{{ $product->category->name ?? 'N/A' }}</td>
                        <td>
                            {{-- {{ $product->sales_channels->name ?? 'N/A' }} --}}
                            {{-- {{ implode(', ', $product->sales_channels->name) }} --}}
                            @foreach ($product->sales_channels as $sales_channel)
                                {{-- {{ $sales_channel['name'] }} --}}
                                <a href="{{ $sales_channel->pivot->listing_url }}" target="_blank">{{ $sales_channel['name'] }}</a>
                            @endforeach
                        </td>
                        <td>{{ \Carbon\Carbon::parse($product->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('products.show', $product->id) }}" class="btn btn-success btn-sm"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" id="product-{{ $product->id }}-delete-form">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <a href="javascript:void(0)" data-id="{{ $product->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $products->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#product-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
