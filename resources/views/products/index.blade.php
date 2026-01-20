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
                            </div>
                        </td>
                        <td>
                            {{ $product->name }}<br>
                            {{ $product->barcode }}
                        </td>
                        <td>{{ $product->price }}</td>
                        <td>{{ $product->product_stocks->sum('quantity'); }}</td>
                        <td>{{ $product->category->name ?? 'N/A' }}</td>
                        <td>
                            {{-- {{ $product->sales_channels->name ?? 'N/A' }} --}}
                            {{ implode(', ', $product->sales_channels['name']) }}
                            {{-- @foreach ($product->sales_channels as $sales_channel)
                                {{ $sales_channel->name }}
                            @endforeach --}}
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
                        <td colspan="8" class="text-center">No Record Found.</td>
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
