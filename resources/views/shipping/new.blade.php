@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Shipping Carrier</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}">Shipping</a></li>
                <li class="breadcrumb-item">Add Carrier</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('shipping.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Shipping</span>
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
        <div class="card-body">
            <form action="{{ route('shipping.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Carrier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. FedEx Production" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Carrier Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Select type</option>
                            <option value="fedex" {{ old('type') === 'fedex' ? 'selected' : '' }}>FedEx</option>
                            <option value="ups"   {{ old('type') === 'ups'   ? 'selected' : '' }}>UPS</option>
                            <option value="usps"  {{ old('type') === 'usps'  ? 'selected' : '' }}>USPS</option>
                            <option value="dhl"   {{ old('type') === 'dhl'   ? 'selected' : '' }}>DHL</option>
                            <option value="other" {{ old('type') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" value="{{ old('account_number') }}" class="form-control" placeholder="Carrier account number">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Default Service Level</label>
                        <select name="default_service" id="defaultService" class="form-select">
                            <option value="">-- Select service (optional) --</option>
                        </select>
                        <small class="text-muted">Select carrier type first to see available services</small>
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="feather-map-pin me-2"></i>Shipper Address <small class="text-muted fw-normal">(Ship From)</small></h6>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company / Sender Name</label>
                        <input type="text" name="shipper_name" value="{{ old('shipper_name') }}" class="form-control" placeholder="e.g. My Store LLC">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Street Address</label>
                        <input type="text" name="shipper_address" value="{{ old('shipper_address') }}" class="form-control" placeholder="123 Warehouse Blvd">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">City</label>
                        <input type="text" name="shipper_city" value="{{ old('shipper_city') }}" class="form-control" placeholder="City">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">State</label>
                        <input type="text" name="shipper_state" value="{{ old('shipper_state') }}" class="form-control" placeholder="TX" maxlength="2">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="shipper_postal_code" value="{{ old('shipper_postal_code') }}" class="form-control" placeholder="77477">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Country</label>
                        <input type="text" name="shipper_country" value="{{ old('shipper_country', 'US') }}" class="form-control" placeholder="US" maxlength="2">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="feather-key me-2"></i>API Credentials</h6>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Client ID / API Key</label>
                        <input type="text" name="client_id" value="{{ old('client_id') }}" class="form-control" placeholder="Client ID or API Key" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Client Secret</label>
                        <input type="password" name="client_secret" class="form-control" placeholder="Client Secret" autocomplete="off">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Live API Endpoint</label>
                        <input type="url" name="api_endpoint" value="{{ old('api_endpoint') }}" class="form-control" placeholder="https://apis.fedex.com">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sandbox API Endpoint</label>
                        <input type="url" name="sandbox_endpoint" value="{{ old('sandbox_endpoint') }}" class="form-control" placeholder="https://apis-sandbox.fedex.com">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tracking URL</label>
                    <input type="url" name="tracking_url" value="{{ old('tracking_url') }}" class="form-control" placeholder="https://www.fedex.com/fedextrack/?trknbr=">
                    <small class="text-muted">Base URL — tracking number will be appended automatically.</small>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="feather-settings me-2"></i>Units & Settings</h6>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Weight Unit <span class="text-danger">*</span></label>
                        <select name="weight_unit" class="form-select" required>
                            <option value="lbs"   {{ old('weight_unit', 'lbs') === 'lbs' ? 'selected' : '' }}>lbs</option>
                            <option value="kg"    {{ old('weight_unit') === 'kg'    ? 'selected' : '' }}>kg</option>
                            <option value="oz"    {{ old('weight_unit') === 'oz'    ? 'selected' : '' }}>oz</option>
                            <option value="g"     {{ old('weight_unit') === 'g'     ? 'selected' : '' }}>g</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Dimension Unit <span class="text-danger">*</span></label>
                        <select name="dimension_unit" class="form-select" required>
                            <option value="inches" {{ old('dimension_unit', 'inches') === 'inches' ? 'selected' : '' }}>inches</option>
                            <option value="cm"     {{ old('dimension_unit') === 'cm'     ? 'selected' : '' }}>cm</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="is_sandbox" name="is_sandbox" value="1" {{ old('is_sandbox') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_sandbox">Use Sandbox / Test Mode</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_default">
                                Set as Default Carrier
                                <small class="text-muted d-block">Only one carrier can be default at a time.</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="is_address_validation" name="is_address_validation" value="1" {{ old('is_address_validation') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_address_validation">
                                Enable Address Validation
                                <small class="text-muted d-block">Only one carrier can validate addresses at a time.</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Save Carrier
                    </button>
                    <a href="{{ route('shipping.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
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

    var oldDefaultService = '{{ old('default_service') }}';

    $('select[name="type"]').on('change', function() {
        var type = $(this).val();
        var $serviceSelect = $('#defaultService');

        $serviceSelect.empty().append('<option value="">-- Select service (optional) --</option>');

        if (type && carrierServices[type]) {
            $.each(carrierServices[type], function(i, service) {
                var selected = (oldDefaultService === service.code) ? ' selected' : '';
                $serviceSelect.append('<option value="' + service.code + '"' + selected + '>' + service.name + '</option>');
            });
        }
    });

    $('select[name="type"]').trigger('change');
});
</script>
@endpush
