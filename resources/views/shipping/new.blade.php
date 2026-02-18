@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Add Shipping Carrier</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('shipping.index') }}">Shipping</a></li>
                        <li class="breadcrumb-item active">Add Carrier</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
<div class="card card-body">
    <form action="{{ route('shipping.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Carrier Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. FedEx Production" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label>Carrier Type <span class="text-danger">*</span></label>
                <select name="type" class="form-control @error('type') is-invalid @enderror" required>
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
                <label>Account Number</label>
                <input type="text" name="account_number" value="{{ old('account_number') }}" class="form-control" placeholder="Carrier account number">
            </div>
            <div class="col-md-6 mb-3">
                <label>Default Service Level</label>
                <input type="text" name="default_service" value="{{ old('default_service') }}" class="form-control" placeholder="e.g. FEDEX_GROUND">
            </div>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">Shipper Address <small class="text-muted font-weight-normal">(Ship From)</small></h6>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Company / Sender Name</label>
                <input type="text" name="shipper_name" value="{{ old('shipper_name') }}" class="form-control" placeholder="e.g. My Store LLC">
            </div>
            <div class="col-md-6 mb-3">
                <label>Street Address</label>
                <input type="text" name="shipper_address" value="{{ old('shipper_address') }}" class="form-control" placeholder="123 Warehouse Blvd">
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>City</label>
                <input type="text" name="shipper_city" value="{{ old('shipper_city') }}" class="form-control" placeholder="City">
            </div>
            <div class="col-md-2 mb-3">
                <label>State</label>
                <input type="text" name="shipper_state" value="{{ old('shipper_state') }}" class="form-control" placeholder="TX" maxlength="2">
            </div>
            <div class="col-md-3 mb-3">
                <label>Postal Code</label>
                <input type="text" name="shipper_postal_code" value="{{ old('shipper_postal_code') }}" class="form-control" placeholder="77477">
            </div>
            <div class="col-md-3 mb-3">
                <label>Country</label>
                <input type="text" name="shipper_country" value="{{ old('shipper_country', 'US') }}" class="form-control" placeholder="US" maxlength="2">
            </div>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">API Credentials</h6>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Client ID / API Key</label>
                <input type="text" name="client_id" value="{{ old('client_id') }}" class="form-control" placeholder="Client ID or API Key" autocomplete="off">
            </div>
            <div class="col-md-6 mb-3">
                <label>Client Secret</label>
                <input type="password" name="client_secret" class="form-control" placeholder="Client Secret" autocomplete="off">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label>Live API Endpoint</label>
                <input type="url" name="api_endpoint" value="{{ old('api_endpoint') }}" class="form-control" placeholder="https://apis.fedex.com">
            </div>
            <div class="col-md-6 mb-3">
                <label>Sandbox API Endpoint</label>
                <input type="url" name="sandbox_endpoint" value="{{ old('sandbox_endpoint') }}" class="form-control" placeholder="https://apis-sandbox.fedex.com">
            </div>
        </div>

        <div class="mb-3">
            <label>Tracking URL</label>
            <input type="url" name="tracking_url" value="{{ old('tracking_url') }}" class="form-control" placeholder="https://www.fedex.com/fedextrack/?trknbr=">
            <small class="text-muted">Base URL — tracking number will be appended automatically.</small>
        </div>

        <hr>
        <h6 class="font-weight-bold mb-3">Units &amp; Settings</h6>

        <div class="row">
            <div class="col-md-3 mb-3">
                <label>Weight Unit <span class="text-danger">*</span></label>
                <select name="weight_unit" class="form-control" required>
                    <option value="lbs"   {{ old('weight_unit', 'lbs') === 'lbs' ? 'selected' : '' }}>lbs</option>
                    <option value="kg"    {{ old('weight_unit') === 'kg'    ? 'selected' : '' }}>kg</option>
                    <option value="oz"    {{ old('weight_unit') === 'oz'    ? 'selected' : '' }}>oz</option>
                    <option value="g"     {{ old('weight_unit') === 'g'     ? 'selected' : '' }}>g</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label>Dimension Unit <span class="text-danger">*</span></label>
                <select name="dimension_unit" class="form-control" required>
                    <option value="inches" {{ old('dimension_unit', 'inches') === 'inches' ? 'selected' : '' }}>inches</option>
                    <option value="cm"     {{ old('dimension_unit') === 'cm'     ? 'selected' : '' }}>cm</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="d-block">&nbsp;</label>
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="is_sandbox" name="is_sandbox" value="1" {{ old('is_sandbox') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_sandbox">Use Sandbox / Test Mode</label>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_default" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_default">
                        Set as Default Carrier
                        <small class="text-muted d-block">Only one carrier can be default at a time.</small>
                    </label>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="is_address_validation" name="is_address_validation" value="1" {{ old('is_address_validation') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_address_validation">
                        Enable Address Validation
                        <small class="text-muted d-block">Only one carrier can validate addresses at a time.</small>
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save Carrier</button>
            <a href="{{ route('shipping.index') }}" class="btn btn-secondary ml-2">Cancel</a>
        </div>
    </form>
</div>
@endsection
