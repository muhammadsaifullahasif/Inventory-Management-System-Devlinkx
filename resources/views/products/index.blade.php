@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Products</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Products</li>
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
                    <a href="{{ route('products.import') }}" class="btn btn-light-brand">
                        <i class="feather-upload me-2"></i>
                        <span>Import</span>
                    </a>
                    <a href="{{ route('products.barcode.bulk-form') }}" class="btn btn-light-brand">
                        <i class="feather-printer me-2"></i>
                        <span>Print Barcodes</span>
                    </a>
                    <a href="{{ route('products.bulk-update.form') }}" class="btn btn-light-brand">
                        <i class="feather-edit me-2"></i>
                        <span>Bulk Update</span>
                    </a>
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

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('products.index') }}" method="GET" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, SKU, Barcode..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Brand</label>
                                <select name="brand_id" class="form-select form-select-sm">
                                    <option value="">All Brands</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Stock Status</label>
                                <select name="stock_status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="in_stock" {{ request('stock_status') == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                                    <option value="out_of_stock" {{ request('stock_status') == 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sales Channel</label>
                                <select name="sales_channel_id" class="form-select form-select-sm">
                                    <option value="">All Channels</option>
                                    @foreach($salesChannels as $channel)
                                        <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>{{ $channel->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('products.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-top mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Barcode</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Category</th>
                                <th>Sales Channels</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr>
                                    <td>{{ $product->id }}</td>
                                    <td>
                                        <div style="max-width: 100px;">
                                            @php
                                                $barcode = (new Picqer\Barcode\Types\TypeCode128())->getBarcode($product->barcode);
                                                $renderer = new Picqer\Barcode\Renderers\SvgRenderer();
                                                $renderer->setSvgType($renderer::TYPE_SVG_STANDALONE);
                                            @endphp
                                            {!! $renderer->render($barcode, 100, 40) !!}
                                            <span class="d-block fs-11 text-muted mt-1">{{ $product->barcode }}</span>
                                            <div class="hstack gap-2 justify-content-start">
                                                <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Print">
                                                    <i class="feather feather-printer"></i>
                                                </a>
                                            </div>
                                            {{-- <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank" class="fs-11">Print</a> --}}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-semibold">{{ $product->name }}</span>
                                        <span class="d-block fs-11 text-muted">SKU: {{ $product->sku }}</span>
                                    </td>
                                    <td><span class="fw-semibold">${{ number_format($product->price, 2) }}</span></td>
                                    <td>
                                        @php $totalStock = $product->product_stocks->sum('quantity'); @endphp
                                        @if($totalStock > 0)
                                            <span class="badge bg-soft-success text-success">{{ $totalStock }}</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger">Out of Stock</span>
                                        @endif
                                    </td>
                                    <td>{{ $product->category->name ?? 'N/A' }}</td>
                                    <td>
                                        @foreach ($product->sales_channels as $sales_channel)
                                            <a href="{{ $sales_channel->pivot->listing_url }}" target="_blank" class="badge bg-soft-primary text-primary me-1">{{ $sales_channel['name'] }}</a>
                                        @endforeach
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($product->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('products.show', $product->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                <i class="feather-eye"></i>
                                            </a>
                                            <div class="dropdown">
                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21" aria-expanded="false">
                                                    <i class="feather feather-more-horizontal"></i>
                                                </a>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a href="{{ route('products.edit', $product->id) }}" class="dropdown-item">
                                                            <i class="feather feather-edit-3 me-2"></i>
                                                            <span>Edit</span>
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a href="{{ route('products.print-barcode', $product->id) }}" target="_blank" class="dropdown-item">
                                                            <i class="feather feather-printer me-2"></i>
                                                            <span>Print Barcode</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('products.destroy', $product->id) }}" method="POST" id="product-{{ $product->id }}-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                        </form>
                                                        <a href="javascript:void(0)" data-id="{{ $product->id }}" class="dropdown-item delete-btn text-danger">
                                                            <i class="feather feather-trash-2 me-2"></i>
                                                            <span>Delete</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            {{-- <a href="{{ route('products.edit', $product->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                <i class="feather-edit-3"></i>
                                            </a>
                                            <a href="javascript:void(0)" data-id="{{ $product->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </a> --}}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No products found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($products->hasPages())
            <div class="card-footer">
                {{ $products->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
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
