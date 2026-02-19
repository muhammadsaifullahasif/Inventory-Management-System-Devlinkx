@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Edit Shipping Carrier</h1>
                    @can('add shipping')
                        <a href="{{ route('shipping.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Carrier</a>
                    @endcan
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}">Shipping</a></li>
                        <li class="breadcrumb-item active">Edit Carrier</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
<div class="card card-body">
    <form action="{{ route('shipping.update', $shipping->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Carrier Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $shipping->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label>Carrier Type <span class="text-danger">*</span></label>
                <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                    <option value="">Select type</option>
                    @foreach (['fedex' => 'FedEx', 'ups' => 'UPS', 'usps' => 'USPS', 'dhl' => 'DHL', 'other' => 'Other'] as $val => $label)
                        <option value="{{ $val }}" {{ old('type', $shipping->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Account Number</label>
                <input type="text" name="account_number" value="{{ old('account_number', $shipping->account_number) }}" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label>Default Service Level</label>
                <select name="default_service" id="defaultService" class="form-control">
                    <option value="">-- Select service (optional) --</option>
                </select>
                <small class="text-muted">Select carrier type first to see available services</small>
            </div>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">Shipper Address <small class="text-muted font-weight-normal">(Ship From)</small></h6>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Company / Sender Name</label>
                <input type="text" name="shipper_name" value="{{ old('shipper_name', $shipping->shipper_name) }}" class="form-control" placeholder="e.g. My Store LLC">
            </div>
            <div class="col-md-6 mb-3">
                <label>Street Address</label>
                <input type="text" name="shipper_address" value="{{ old('shipper_address', $shipping->shipper_address) }}" class="form-control" placeholder="123 Warehouse Blvd">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>City</label>
                <input type="text" name="shipper_city" value="{{ old('shipper_city', $shipping->shipper_city) }}" class="form-control" placeholder="City">
            </div>
            <div class="col-md-2 mb-3">
                <label>State</label>
                <input type="text" name="shipper_state" value="{{ old('shipper_state', $shipping->shipper_state) }}" class="form-control" placeholder="TX" maxlength="2">
            </div>
            <div class="col-md-3 mb-3">
                <label>Postal Code</label>
                <input type="text" name="shipper_postal_code" value="{{ old('shipper_postal_code', $shipping->shipper_postal_code) }}" class="form-control" placeholder="77477">
            </div>
            <div class="col-md-3 mb-3">
                <label>Country</label>
                <input type="text" name="shipper_country" value="{{ old('shipper_country', $shipping->shipper_country ?? 'US') }}" class="form-control" placeholder="US" maxlength="2">
            </div>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">API Credentials</h6>
        <p class="text-muted small">Leave blank to keep existing credentials.</p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Client ID / API Key</label>
                <input type="text" name="client_id" value="" class="form-control" placeholder="Leave blank to keep existing" autocomplete="off">
            </div>
            <div class="col-md-6 mb-3">
                <label>Client Secret</label>
                <input type="password" name="client_secret" value="" class="form-control" placeholder="Leave blank to keep existing" autocomplete="off">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Live API Endpoint</label>
                <input type="url" name="api_endpoint" value="{{ old('api_endpoint', $shipping->api_endpoint) }}" class="form-control" placeholder="https://apis.fedex.com">
            </div>
            <div class="col-md-6 mb-3">
                <label>Sandbox API Endpoint</label>
                <input type="url" name="sandbox_endpoint" value="{{ old('sandbox_endpoint', $shipping->sandbox_endpoint) }}" class="form-control" placeholder="https://apis-sandbox.fedex.com">
            </div>
        </div>

        <div class="mb-3">
            <label>Tracking URL</label>
            <input type="url" name="tracking_url" value="{{ old('tracking_url', $shipping->tracking_url) }}" class="form-control" placeholder="https://www.fedex.com/fedextrack/?trknbr=">
            <small class="text-muted">Base URL — tracking number will be appended automatically.</small>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">Units &amp; Settings</h6>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label>Weight Unit <span class="text-danger">*</span></label>
                <select name="weight_unit" class="form-control" required>
                    @foreach (['lbs', 'kg', 'oz', 'g'] as $unit)
                        <option value="{{ $unit }}" {{ old('weight_unit', $shipping->weight_unit) === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label>Dimension Unit <span class="text-danger">*</span></label>
                <select name="dimension_unit" class="form-control" required>
                    @foreach (['inches', 'cm'] as $unit)
                        <option value="{{ $unit }}" {{ old('dimension_unit', $shipping->dimension_unit) === $unit ? 'selected' : '' }}>{{ $unit }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="d-block">&nbsp;</label>
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="is_sandbox" name="is_sandbox" value="1"
                        {{ old('is_sandbox', $shipping->is_sandbox) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_sandbox">Use Sandbox / Test Mode</label>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1"
                        {{ old('is_default', $shipping->is_default) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_default">
                        Set as Default Carrier
                        <small class="text-muted d-block">Only one carrier can be default at a time.</small>
                    </label>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_address_validation" name="is_address_validation" value="1"
                        {{ old('is_address_validation', $shipping->is_address_validation) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_address_validation">
                        Enable Address Validation
                        <small class="text-muted d-block">Only one carrier can validate addresses at a time.</small>
                    </label>
                </div>
            </div>
        </div>

        @if ($shipping->access_token)
            <div class="alert alert-info mt-2">
                <i class="fas fa-key mr-1"></i>
                Active token expires:
                <strong>{{ $shipping->access_token_expires_at ? \Carbon\Carbon::parse($shipping->access_token_expires_at)->format('M d, Y H:i') : 'Unknown' }}</strong>
            </div>
        @endif

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Update Carrier</button>
            <a href="{{ route('shipping.index') }}" class="btn btn-secondary ml-2">Cancel</a>
            @can('view shipping')
                <a href="{{ route('shipping.show', $shipping->id) }}" class="btn btn-info ml-2">View Details</a>
            @endcan
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Carrier services mapping
    var carrierServices = {
        fedex: [
            { code: 'FEDEX_GROUND', name: 'FedEx Ground' },
            { code: 'FEDEX_HOME_DELIVERY', name: 'FedEx Home Delivery' },
            { code: 'FEDEX_EXPRESS_SAVER', name: 'FedEx Express Saver' },
            { code: 'FEDEX_2_DAY', name: 'FedEx 2Day' },
            { code: 'FEDEX_2_DAY_AM', name: 'FedEx 2Day AM' },
            { code: 'STANDARD_OVERNIGHT', name: 'FedEx Standard Overnight' },
            { code: 'PRIORITY_OVERNIGHT', name: 'FedEx Priority Overnight' },
            { code: 'FIRST_OVERNIGHT', name: 'FedEx First Overnight' },
            { code: 'GROUND_HOME_DELIVERY', name: 'FedEx Ground Home Delivery' },
            { code: 'SMART_POST', name: 'FedEx SmartPost' },
            { code: 'FEDEX_FREIGHT_ECONOMY', name: 'FedEx Freight Economy' },
            { code: 'FEDEX_FREIGHT_PRIORITY', name: 'FedEx Freight Priority' },
            { code: 'INTERNATIONAL_ECONOMY', name: 'FedEx International Economy' },
            { code: 'INTERNATIONAL_PRIORITY', name: 'FedEx International Priority' },
            { code: 'INTERNATIONAL_FIRST', name: 'FedEx International First' },
            { code: 'INTERNATIONAL_GROUND', name: 'FedEx International Ground' }
        ],
        ups: [
            { code: 'UPS_GROUND', name: 'UPS Ground' },
            { code: 'UPS_3_DAY_SELECT', name: 'UPS 3 Day Select' },
            { code: 'UPS_2ND_DAY_AIR', name: 'UPS 2nd Day Air' },
            { code: 'UPS_2ND_DAY_AIR_AM', name: 'UPS 2nd Day Air AM' },
            { code: 'UPS_NEXT_DAY_AIR_SAVER', name: 'UPS Next Day Air Saver' },
            { code: 'UPS_NEXT_DAY_AIR', name: 'UPS Next Day Air' },
            { code: 'UPS_NEXT_DAY_AIR_EARLY', name: 'UPS Next Day Air Early' },
            { code: 'UPS_SUREPOST', name: 'UPS SurePost' }
        ],
        usps: [
            { code: 'USPS_PRIORITY_MAIL', name: 'USPS Priority Mail' },
            { code: 'USPS_PRIORITY_MAIL_EXPRESS', name: 'USPS Priority Mail Express' },
            { code: 'USPS_FIRST_CLASS', name: 'USPS First Class' },
            { code: 'USPS_PARCEL_SELECT', name: 'USPS Parcel Select' },
            { code: 'USPS_MEDIA_MAIL', name: 'USPS Media Mail' },
            { code: 'USPS_RETAIL_GROUND', name: 'USPS Retail Ground' }
        ],
        dhl: [
            { code: 'DHL_EXPRESS_WORLDWIDE', name: 'DHL Express Worldwide' },
            { code: 'DHL_EXPRESS_12', name: 'DHL Express 12:00' },
            { code: 'DHL_EXPRESS_9', name: 'DHL Express 9:00' },
            { code: 'DHL_ECONOMY_SELECT', name: 'DHL Economy Select' }
        ]
    };

    var currentDefaultService = '{{ old('default_service', $shipping->default_service) }}';

    // Populate services dropdown when carrier type changes
    $('select[name="type"]').on('change', function() {
        var type = $(this).val();
        var $serviceSelect = $('#defaultService');

        $serviceSelect.empty().append('<option value="">-- Select service (optional) --</option>');

        if (type && carrierServices[type]) {
            $.each(carrierServices[type], function(i, service) {
                var selected = (currentDefaultService === service.code) ? ' selected' : '';
                $serviceSelect.append('<option value="' + service.code + '"' + selected + '>' + service.name + '</option>');
            });
        }
    });

    // Trigger on page load to populate services for existing carrier type
    $('select[name="type"]').trigger('change');
});
</script>
@endpush
