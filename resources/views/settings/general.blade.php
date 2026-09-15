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
                <li class="breadcrumb-item">General</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    @include('settings._nav')

    {{-- @if(session('success'))
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
    @endif --}}

    <div class="col-12">
        <form action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-tag me-2"></i>Branding</h5>
                </div>
                <div class="card-body">
                    <label for="app_name" class="form-label">App name</label>
                    <input type="text" class="form-control @error('app_name') is-invalid @enderror" id="app_name" name="app_name" value="{{ old('app_name', $settings->app_name) }}" style="max-width: 320px;">
                    @error('app_name')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="fs-12 text-muted mt-1 mb-3">
                        Shown in the browser title and the sidebar brand.
                    </div>

                    <label for="logo" class="form-label d-block">Logo</label>
                    <div class="d-flex align-items-center gap-3">
                        <img src="{{ $settings->logo ? asset($settings->logo) : asset('images/sigma-body-parts-logo.png') }}" alt="Logo" style="width: 56px; height: 56px; object-fit: contain; border: 1px solid #e5e5e5; border-radius: 4px; padding: 4px;">
                        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept=".jpg,.jpeg,.png,.svg" style="max-width: 320px;">
                    </div>
                    @error('logo')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="fs-12 text-muted mt-2">
                        JPG, PNG or SVG, up to 2MB. Leave empty to keep the current logo.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-mail me-2"></i>Admin Contact</h5>
                </div>
                <div class="card-body">
                    <label for="admin_email" class="form-label">Admin email</label>
                    <input type="email" class="form-control @error('admin_email') is-invalid @enderror" id="admin_email" name="admin_email" value="{{ old('admin_email', $settings->admin_email) }}" placeholder="admin@example.com" style="max-width: 320px;">
                    @error('admin_email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-globe me-2"></i>Localization</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="date_format" class="form-label">Date format</label>
                            @php($today = \Carbon\Carbon::now())
                            <select class="form-select @error('date_format') is-invalid @enderror" id="date_format" name="date_format">
                                @foreach(['F d, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'M d, Y', 'd M Y'] as $format)
                                    <option value="{{ $format }}" {{ old('date_format', $settings->date_format) === $format ? 'selected' : '' }}>
                                        {{ $format }} ({{ $today->format($format) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="week_start_day" class="form-label">Start of the week</label>
                            <select class="form-select @error('week_start_day') is-invalid @enderror" id="week_start_day" name="week_start_day">
                                @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $i => $day)
                                    <option value="{{ $i }}" {{ (int) old('week_start_day', $settings->week_start_day) === $i ? 'selected' : '' }}>
                                        {{ $day }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
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
