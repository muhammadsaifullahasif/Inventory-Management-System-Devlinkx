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
    <div class="card mb-3 d-print-none">
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
        <div class="card-body p-0">
            @include("products.barcode")
        </div>
        <div class="card-footer">
            <button onclick="window.print()" class="btn btn-primary d-print-none">Print Barcodes</button>
        </div>
    </div>
@endsection
