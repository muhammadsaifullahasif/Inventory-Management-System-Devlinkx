@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Supplier</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
                <li class="breadcrumb-item">Add Supplier</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Suppliers</span>
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
                <h5 class="card-title">Supplier Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('suppliers.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="form-control @error('first_name') is-invalid @enderror" placeholder="First Name">
                            @error('first_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="form-control @error('last_name') is-invalid @enderror" placeholder="Last Name">
                            @error('last_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Email">
                            @error('email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone', '+1') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="Phone">
                            @error('phone')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company" class="form-label">Company</label>
                            <input type="text" id="company" name="company" value="{{ old('company') }}" class="form-control @error('company') is-invalid @enderror" placeholder="Company">
                            @error('company')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" id="designation" name="designation" value="{{ old('designation') }}" class="form-control @error('designation') is-invalid @enderror" placeholder="Designation">
                            @error('designation')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                            <input type="text" id="address_line_1" name="address_line_1" value="{{ old('address_line_1') }}" class="form-control @error('address_line_1') is-invalid @enderror" placeholder="Address Line 1">
                            @error('address_line_1')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="address_line_2" class="form-label">Address Line 2</label>
                            <input type="text" id="address_line_2" name="address_line_2" value="{{ old('address_line_2') }}" class="form-control @error('address_line_2') is-invalid @enderror" placeholder="Address Line 2">
                            @error('address_line_2')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="country" class="form-label">Country</label>
                            <input type="text" id="country" name="country" value="{{ old('country') }}" class="form-control @error('country') is-invalid @enderror" placeholder="Country">
                            @error('country')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">State</label>
                            <input type="text" id="state" name="state" value="{{ old('state') }}" class="form-control @error('state') is-invalid @enderror" placeholder="State">
                            @error('state')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" id="city" name="city" value="{{ old('city') }}" class="form-control @error('city') is-invalid @enderror" placeholder="City">
                            @error('city')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="zipcode" class="form-label">Zipcode</label>
                            <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode') }}" class="form-control @error('zipcode') is-invalid @enderror" placeholder="Zipcode">
                            @error('zipcode')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="active_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="active_status" id="active_status" class="form-select @error('active_status') is-invalid @enderror">
                                <option value="">Select Status</option>
                                <option value="1" {{ (old('active_status') == '1') ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ (old('active_status') == '0') ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('active_status')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-2"></i>Save Supplier
                        </button>
                        <a href="{{ route('suppliers.index') }}" class="btn btn-light-brand">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
