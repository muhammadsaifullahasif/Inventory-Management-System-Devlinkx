@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Sales Channel</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('sales-channels.index') }}">Sales Channels</a></li>
                <li class="breadcrumb-item">Add Sales Channel</li>
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
                <h5 class="card-title">eBay Connection Details</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('sales-channels.store') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="client_id" class="form-label">Client ID <span class="text-danger">*</span></label>
                            <input type="text" name="client_id" id="client_id" value="{{ old('client_id') }}" class="form-control @error('client_id') is-invalid @enderror" required>
                            @error('client_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="client_secret" class="form-label">Client Secret <span class="text-danger">*</span></label>
                            <input type="password" name="client_secret" id="client_secret" value="{{ old('client_secret') }}" class="form-control @error('client_secret') is-invalid @enderror" required>
                            @error('client_secret')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="ru_name" class="form-label">RU Name <span class="text-danger">*</span></label>
                            <input type="text" name="ru_name" id="ru_name" value="{{ old('ru_name') }}" class="form-control @error('ru_name') is-invalid @enderror" required>
                            @error('ru_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="user_scopes" class="form-label">User Scopes <span class="text-danger">*</span></label>
                            <input type="text" name="user_scopes" id="user_scopes" value="{{ old('user_scopes') }}" class="form-control @error('user_scopes') is-invalid @enderror" required>
                            @error('user_scopes')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-link me-2"></i>Connect with eBay
                        </button>
                        <a href="{{ route('sales-channels.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
