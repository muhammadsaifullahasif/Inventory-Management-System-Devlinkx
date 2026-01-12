@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Sales Channel New</h1>
                    @can('add purchases')
                        <a href="{{ route('sales-channels.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Sales Channel</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('sales-channels.index') }}">Sales Channels</a></li>
                        <li class="breadcrumb-item active">Sales Channel New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('sales-channels.store') }}" method="post">
            @csrf
            <div class="mb-3">
                <label for="name">Connect with Ebay: <span class="text-danger">*</span></label>
            </div>
            <button type="submit" class="btn btn-primary">Connect with Ebay</button>
        </form>
    </div>
@endsection
