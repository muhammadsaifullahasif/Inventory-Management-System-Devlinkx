@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">{{ $shipping->name }}</h1>
                    @can('add shipping')
                        <a href="{{ route('shipping.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Carrier</a>
                    @endcan
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}">Shipping</a></li>
                        <li class="breadcrumb-item active">{{ $shipping->name }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card card-body mb-3">
            <h5 class="border-bottom pb-2 mb-3">Carrier Details</h5>

            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Name</div>
                <div class="col-sm-8"><strong>{{ $shipping->name }}</strong></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Type</div>
                <div class="col-sm-8"><span class="badge badge-secondary">{{ strtoupper($shipping->type) }}</span></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Account Number</div>
                <div class="col-sm-8">{{ $shipping->account_number ?: '-' }}</div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Default Service</div>
                <div class="col-sm-8">{{ $shipping->default_service ?: '-' }}</div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Weight Unit</div>
                <div class="col-sm-8">{{ $shipping->weight_unit }}</div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Dimension Unit</div>
                <div class="col-sm-8">{{ $shipping->dimension_unit }}</div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Tracking URL</div>
                <div class="col-sm-8">
                    @if ($shipping->tracking_url)
                        <a href="{{ $shipping->tracking_url }}" target="_blank" rel="noopener">{{ $shipping->tracking_url }}</a>
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-body mb-3">
            <h5 class="border-bottom pb-2 mb-3">API Configuration</h5>

            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Mode</div>
                <div class="col-sm-8">
                    @if ($shipping->is_sandbox)
                        <span class="badge badge-warning">Sandbox / Test</span>
                    @else
                        <span class="badge badge-success">Live / Production</span>
                    @endif
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Live API Endpoint</div>
                <div class="col-sm-8"><code>{{ $shipping->api_endpoint ?: '-' }}</code></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Sandbox Endpoint</div>
                <div class="col-sm-8"><code>{{ $shipping->sandbox_endpoint ?: '-' }}</code></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Client ID</div>
                <div class="col-sm-8">
                    @if (!empty($shipping->credentials['client_id']))
                        <span class="text-muted">{{ substr($shipping->credentials['client_id'], 0, 6) }}••••••••</span>
                    @else
                        <span class="text-muted">Not set</span>
                    @endif
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Client Secret</div>
                <div class="col-sm-8">
                    @if (!empty($shipping->credentials['client_secret']))
                        <span class="text-muted">••••••••••••</span>
                    @else
                        <span class="text-muted">Not set</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-body mb-3">
            <h5 class="border-bottom pb-2 mb-3">Status</h5>

            <div class="row mb-2">
                <div class="col-sm-6 text-muted">Active</div>
                <div class="col-sm-6">
                    <span class="badge badge-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }}">
                        {{ $shipping->active_status === '1' ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-6 text-muted">Default Carrier</div>
                <div class="col-sm-6">
                    @if ($shipping->is_default)
                        <span class="badge badge-primary">Yes</span>
                    @else
                        <span class="text-muted">No</span>
                    @endif
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-6 text-muted">Address Validation</div>
                <div class="col-sm-6">
                    @if ($shipping->is_address_validation)
                        <span class="badge badge-info">Enabled</span>
                    @else
                        <span class="text-muted">Disabled</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-body mb-3">
            <h5 class="border-bottom pb-2 mb-3">Access Token</h5>

            @if ($shipping->access_token)
                <div class="mb-2">
                    <small class="text-muted">Expires at</small><br>
                    <strong>{{ $shipping->access_token_expires_at ? \Carbon\Carbon::parse($shipping->access_token_expires_at)->format('M d, Y H:i') : 'Unknown' }}</strong>
                </div>
                <div>
                    @if ($shipping->access_token_expires_at && \Carbon\Carbon::parse($shipping->access_token_expires_at)->isFuture())
                        <span class="badge badge-success">Valid</span>
                    @else
                        <span class="badge badge-danger">Expired</span>
                    @endif
                </div>
            @else
                <p class="text-muted mb-0">No token stored yet.</p>
            @endif
        </div>

        <div class="card card-body">
            <h5 class="border-bottom pb-2 mb-3">Actions</h5>
            @can('edit shipping')
                <a href="{{ route('shipping.edit', $shipping->id) }}" class="btn btn-warning btn-block mb-2">
                    <i class="fas fa-edit mr-1"></i> Edit Carrier
                </a>
            @endcan
            @can('delete shipping')
                <form action="{{ route('shipping.destroy', $shipping->id) }}" method="POST" class="delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-trash mr-1"></i> Delete Carrier
                    </button>
                </form>
            @endcan
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).on('submit', '.delete-form', function (e) {
    e.preventDefault();
    if (confirm('Delete this shipping carrier? This cannot be undone.')) {
        $(this).off('submit').submit();
    }
});
</script>
@endpush
