@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Supplier New</h1>
                    @can('add suppliers')
                        <a href="{{ route('suppliers.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Supplier</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
                        <li class="breadcrumb-item active">Supplier New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('suppliers.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name">First Name: <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="form-control @error('first_name') is-invalid @enderror" placeholder="First Name">
                    @error('first_name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name">Last Name: <span class="text-danger">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="form-control @error('last_name') is-invalid @enderror" placeholder="Last Name">
                    @error('last_name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email">Email: <span class="text-danger">*</span></label>
                    <input type="text" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Email">
                    @error('email')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone">Phone: <span class="text-danger">*</span></label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', '+1') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="Phone">
                    @error('phone')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="company">Company:</label>
                    <input type="text" id="company" name="company" value="{{ old('company') }}" class="form-control @error('company') is-invalid @enderror" placeholder="Company">
                    @error('company')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="designation">Designation:</label>
                    <input type="text" id="designation" name="designation" value="{{ old('designation') }}" class="form-control @error('designation') is-invalid @enderror" placeholder="Designation">
                    @error('designation')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="address_line_1">Address Line 1: <span class="text-danger">*</span></label>
                    <input type="text" id="address_line_1" name="address_line_1" value="{{ old('address_line_1') }}" class="form-control @error('address_line_1') is-invalid @enderror" placeholder="Address Line 1">
                    @error('address_line_1')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="address_line_2">Address Line 2:</label>
                    <input type="text" id="address_line_2" name="address_line_2" value="{{ old('address_line_2') }}" class="form-control @error('address_line_2') is-invalid @enderror" placeholder="Address Line 2">
                    @error('address_line_2')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" value="{{ old('country') }}" class="form-control @error('country') is-invalid @enderror" placeholder="Country">
                    @error('country')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="state">State:</label>
                    <input type="text" id="state" name="state" value="{{ old('state') }}" class="form-control @error('state') is-invalid @enderror" placeholder="State">
                    @error('state')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" value="{{ old('city') }}" class="form-control @error('city') is-invalid @enderror" placeholder="City">
                    @error('city')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="zipcode">Zipcode:</label>
                    <input type="text" id="zipcode" name="zipcode" value="{{ old('zipcode') }}" class="form-control @error('zipcode') is-invalid @enderror" placeholder="Zipcode">
                    @error('zipcode')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="active_status">Status: <span class="text-danger">*</span></label>
                    <select name="active_status" id="active_status" class="form-control @error('active_status') is-invalid @enderror">
                        <option value="">Select Status</option>
                        <option value="1" {{ (old('active_status') == '1') ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ (old('active_status') == '0') ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('active_status')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
