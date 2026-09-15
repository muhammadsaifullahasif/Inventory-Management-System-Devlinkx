@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item">Shipping</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    @include('settings._nav')

    @if(session('success'))
        <div class="col-12">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="col-12">
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="col-12">
        <form action="{{ route('settings.shipping.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-clock me-2"></i>eBay Shipment Cutoff</h5>
                </div>
                <div class="card-body">
                    <label for="cutoff_hour" class="form-label">Cutoff hour (Central Time)</label>
                    <select class="form-select @error('cutoff_hour') is-invalid @enderror" id="cutoff_hour" name="cutoff_hour" style="max-width: 220px;">
                        @for($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}" {{ (int) old('cutoff_hour', $settings->cutoff_hour) === $h ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromTime($h, 0)->format('g:i A') }}
                            </option>
                        @endfor
                    </select>
                    <div class="fs-12 text-muted mt-2">
                        Only used as fallback when eBay doesn't send an explicit <code>HandleByTime</code> for an order. If the order's paid/created time (Central) is at or after this hour, the calculated shipment deadline pushes to the next business day. Default: 2:00 PM.
                    </div>
                </div>
            </div>

            <div class="col-12 mb-4">
                <button type="submit" class="btn btn-primary">
                    <i class="feather-save me-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
@endsection
