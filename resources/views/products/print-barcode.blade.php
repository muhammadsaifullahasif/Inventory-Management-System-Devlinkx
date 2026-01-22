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
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="">
                @csrf
                <div class="mb-3">
                    <label for="number_of_barcode">Number of Barcodes:</label>
                    <input type="text" id="number_of_barcode" name="number_of_barcode" class="form-control" placeholder="Enter number of barcodes to print" value="1">
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div style="width: 100%; display: flex; flex-wrap: wrap; justify-content: space-evenly; align-items: center;">
                {{-- @if ( $_GET['number_of_barcode'] != '' ) --}}
                    @for ($i = 0; $i < ( isset($_GET['number_of_barcode']) ? $_GET['number_of_barcode'] : 21 ); $i++)
                        <div style="margin: 10px; text-align: center; border: 1px solid #000; padding: 10px; max-width: 33.33%; width: 100%;">
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
                            <div>{{ $product->barcode }}</div>
                        </div>
                    @endfor
                {{-- @else --}}
                    {{-- <p>No barcodes to display.</p> --}}
                {{-- @endif --}}
            </div>
        </div>
    </div>
@endsection
