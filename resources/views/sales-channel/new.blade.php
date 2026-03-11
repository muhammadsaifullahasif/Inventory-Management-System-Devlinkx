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
                    Click the button below to connect your eBay store. You will be redirected to eBay to authorize access.
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
                            <label for="name" class="form-label">Store Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g., My eBay Store" required>
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Enter a friendly name to identify this eBay store</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="client_id" class="form-label">Client ID (App ID) <span class="text-danger">*</span></label>
                            <input type="text" name="client_id" id="client_id" value="{{ old('client_id') }}"
                                   class="form-control @error('client_id') is-invalid @enderror"
                                   placeholder="Enter your eBay App ID" required>
                            @error('client_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">From your eBay Developer Application</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="client_secret" class="form-label">Client Secret (Cert ID) <span class="text-danger">*</span></label>
                            <input type="password" name="client_secret" id="client_secret" value="{{ old('client_secret') }}"
                                   class="form-control @error('client_secret') is-invalid @enderror"
                                   placeholder="Enter your eBay Cert ID" required>
                            @error('client_secret')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Keep this secret secure</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="ru_name" class="form-label">RuName (Redirect URI) <span class="text-danger">*</span></label>
                            <input type="text" name="ru_name" id="ru_name" value="{{ old('ru_name', url('/ebay/callback')) }}"
                                   class="form-control @error('ru_name') is-invalid @enderror"
                                   placeholder="{{ url('/ebay/callback') }}" required>
                            @error('ru_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">OAuth redirect URI configured in eBay Developer</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label for="user_scopes" class="form-label">User Scopes <span class="text-danger">*</span></label>
                            <textarea name="user_scopes" id="user_scopes" rows="3"
                                      class="form-control @error('user_scopes') is-invalid @enderror"
                                      placeholder="Enter eBay API scopes (space-separated)" required>{{ old('user_scopes', 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.marketing.readonly https://api.ebay.com/oauth/api_scope/sell.marketing https://api.ebay.com/oauth/api_scope/sell.inventory.readonly https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.account.readonly https://api.ebay.com/oauth/api_scope/sell.account https://api.ebay.com/oauth/api_scope/sell.fulfillment.readonly https://api.ebay.com/oauth/api_scope/sell.fulfillment https://api.ebay.com/oauth/api_scope/sell.analytics.readonly https://api.ebay.com/oauth/api_scope/sell.finances https://api.ebay.com/oauth/api_scope/sell.payment.dispute https://api.ebay.com/oauth/api_scope/commerce.identity.readonly https://api.ebay.com/oauth/api_scope/commerce.notification.subscription https://api.ebay.com/oauth/api_scope/commerce.notification.subscription.readonly https://api.ebay.com/oauth/api_scope/sell.stores https://api.ebay.com/oauth/api_scope/sell.stores.readonly') }}</textarea>
                            @error('user_scopes')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                            <small class="text-muted">Space-separated list of OAuth scopes</small>
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
