@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">{{ $shipping->name }}</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}">Shipping</a></li>
                <li class="breadcrumb-item">{{ $shipping->name }}</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('shipping.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Shipping</span>
                    </a>
                    @can('edit shipping')
                        <a href="{{ route('shipping.edit', $shipping->id) }}" class="btn btn-primary">
                            <i class="feather-edit-3 me-2"></i>
                            <span>Edit Carrier</span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <!-- Carrier Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-truck me-2"></i>Carrier Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Name</div>
                    <div class="col-sm-8"><span class="fw-semibold">{{ $shipping->name }}</span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Type</div>
                    <div class="col-sm-8"><span class="badge bg-soft-secondary text-secondary">{{ strtoupper($shipping->type) }}</span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Account Number</div>
                    <div class="col-sm-8">{{ $shipping->account_number ?: '-' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Default Service</div>
                    <div class="col-sm-8">{{ $shipping->default_service ?: '-' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Ship From (Shipper)</div>
                    <div class="col-sm-8">
                        @php
                            $shipperParts = array_filter([
                                $shipping->shipper_name,
                                $shipping->shipper_address,
                                $shipping->shipper_city,
                                $shipping->shipper_state,
                                $shipping->shipper_postal_code,
                                $shipping->shipper_country,
                            ]);
                        @endphp
                        {{ $shipperParts ? implode(', ', $shipperParts) : '-' }}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Weight Unit</div>
                    <div class="col-sm-8">{{ $shipping->weight_unit }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Dimension Unit</div>
                    <div class="col-sm-8">{{ $shipping->dimension_unit }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Tracking URL</div>
                    <div class="col-sm-8">
                        @if ($shipping->tracking_url)
                            <a href="{{ $shipping->tracking_url }}" target="_blank" rel="noopener" class="text-primary">
                                {{ $shipping->tracking_url }}
                                <i class="feather-external-link ms-1"></i>
                            </a>
                        @else
                            -
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- API Configuration -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-settings me-2"></i>API Configuration</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Mode</div>
                    <div class="col-sm-8">
                        @if ($shipping->is_sandbox)
                            <span class="badge bg-soft-warning text-warning">Sandbox / Test</span>
                        @else
                            <span class="badge bg-soft-success text-success">Live / Production</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Live API Endpoint</div>
                    <div class="col-sm-8"><code>{{ $shipping->api_endpoint ?: '-' }}</code></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Sandbox Endpoint</div>
                    <div class="col-sm-8"><code>{{ $shipping->sandbox_endpoint ?: '-' }}</code></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Client ID</div>
                    <div class="col-sm-8">
                        @if (!empty($shipping->credentials['client_id']))
                            <span class="text-muted">{{ substr($shipping->credentials['client_id'], 0, 6) }}••••••••</span>
                        @else
                            <span class="text-muted">Not set</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-3">
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
    </div>

    <div class="col-lg-4">
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-activity me-2"></i>Status</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6 text-muted">Active</div>
                    <div class="col-6">
                        <span class="badge bg-soft-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }} text-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }}">
                            {{ $shipping->active_status === '1' ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6 text-muted">Default Carrier</div>
                    <div class="col-6">
                        @if ($shipping->is_default)
                            <span class="badge bg-soft-primary text-primary">Yes</span>
                        @else
                            <span class="text-muted">No</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6 text-muted">Address Validation</div>
                    <div class="col-6">
                        @if ($shipping->is_address_validation)
                            <span class="badge bg-soft-info text-info">Enabled</span>
                        @else
                            <span class="text-muted">Disabled</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Access Token Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-key me-2"></i>Access Token</h5>
            </div>
            <div class="card-body">
                @if ($shipping->access_token)
                    <div class="mb-2">
                        <small class="text-muted">Expires at</small><br>
                        <span class="fw-semibold">{{ $shipping->access_token_expires_at ? \Carbon\Carbon::parse($shipping->access_token_expires_at)->format('M d, Y H:i') : 'Unknown' }}</span>
                    </div>
                    <div>
                        @if ($shipping->access_token_expires_at && \Carbon\Carbon::parse($shipping->access_token_expires_at)->isFuture())
                            <span class="badge bg-soft-success text-success">Valid</span>
                        @else
                            <span class="badge bg-soft-danger text-danger">Expired</span>
                        @endif
                    </div>
                @else
                    <p class="text-muted mb-0">No token stored yet.</p>
                @endif
            </div>
        </div>

        <!-- Actions Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-zap me-2"></i>Actions</h5>
            </div>
            <div class="card-body">
                @can('edit shipping')
                    <a href="{{ route('shipping.edit', $shipping->id) }}" class="btn btn-warning w-100 mb-2">
                        <i class="feather-edit-3 me-2"></i>Edit Carrier
                    </a>
                @endcan
                @can('delete shipping')
                    <form action="{{ route('shipping.destroy', $shipping->id) }}" method="POST" class="delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="feather-trash-2 me-2"></i>Delete Carrier
                        </button>
                    </form>
                @endcan
            </div>
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
