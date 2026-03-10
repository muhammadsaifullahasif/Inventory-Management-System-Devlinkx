@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Sales Channel</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('sales-channels.index') }}">Sales Channels</a></li>
                <li class="breadcrumb-item">Edit Sales Channel</li>
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
                <h5 class="card-title">Edit Sales Channel</h5>
            </div>
            <div class="card-body">
                <!-- Connection Status -->
                <div class="mb-4">
                    @if($sales_channel->hasValidToken())
                        <div class="alert alert-success">
                            <i class="feather-check-circle me-2"></i>
                            <strong>Connected</strong> - This eBay store is connected and active.
                            @if($sales_channel->access_token_expires_at)
                                <br><small class="text-muted">Token expires: {{ $sales_channel->access_token_expires_at->format('M d, Y H:i') }}</small>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="feather-alert-circle me-2"></i>
                            <strong>Disconnected</strong> - Please reconnect to eBay to continue syncing.
                        </div>
                    @endif
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

                <form action="{{ route('sales-channels.update', $sales_channel->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Store Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name"
                                   value="{{ old('name', $sales_channel->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Save Changes
                        </button>
                        <button type="submit" name="reconnect" value="1" class="btn btn-warning">
                            <i class="feather-refresh-cw me-2"></i>Reconnect with eBay
                        </button>
                        <a href="{{ route('sales-channels.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
