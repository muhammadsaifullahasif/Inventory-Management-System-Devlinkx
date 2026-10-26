@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Connect eBay Store</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('sales-channels.index') }}">Sales Channels</a></li>
                <li class="breadcrumb-item">Connect eBay Store</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('sales-channels.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Sales Channels</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Connect eBay Store</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <i class="feather-info me-2"></i>
                    You will be sent to eBay to sign in. Sign in with the eBay account of the store you want to connect;
                    tokens, notifications and the webhook are set up automatically when you return.
                    To connect another store, repeat this and sign in with that store's account.
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger mb-4">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('sales-channels.store') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Store Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g., My eBay Store">
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Optional; defaults to the eBay username you sign in with</small>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-log-in me-2"></i>Login with eBay
                        </button>
                        <a href="{{ route('sales-channels.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
