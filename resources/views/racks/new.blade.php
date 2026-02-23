@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Add Rack</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                <li class="breadcrumb-item">Add Rack</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('racks.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Racks</span>
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
            <form action="{{ route('racks.store') }}" method="post">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Rack Name" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                        <option value="">Select Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (old('warehouse_id') == $warehouse->id || $warehouse->is_default) ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" id="is_default" value="1" name="is_default" class="form-check-input" {{ old('is_default') == 1 ? 'checked' : '' }}>
                        <label for="is_default" class="form-check-label">Is Default</label>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Save Rack
                    </button>
                    <a href="{{ route('racks.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
