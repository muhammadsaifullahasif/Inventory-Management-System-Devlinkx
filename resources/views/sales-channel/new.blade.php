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
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="client_id">Client ID: <span class="text-danger">*</span></label>
                <input type="text" name="client_id" id="client_id" value="{{ old('client_id') }}" class="form-control @error('client_id') is-invalid @enderror" required>
                @error('client_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="client_secret">Client Secret: <span class="text-danger">*</span></label>
                <input type="password" name="client_secret" id="client_secret" class="form-control @error('client_secret') is-invalid @enderror" required>
                @error('client_secret')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="ru_name">RU Name: <span class="text-danger">*</span></label>
                <input type="text" name="ru_name" id="ru_name" value="{{ old('ru_name') }}" class="form-control @error('ru_name') is-invalid @enderror" required>
                @error('ru_name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="user_scopes">User Scopes: <span class="text-danger">*</span></label>
                <input type="text" name="user_scopes" id="user_scopes" value="{{ old('user_scopes') }}" class="form-control @error('user_scopes') is-invalid @enderror" required>
                @error('user_scopes')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Connect with Ebay</button>
        </form>
    </div>
@endsection
