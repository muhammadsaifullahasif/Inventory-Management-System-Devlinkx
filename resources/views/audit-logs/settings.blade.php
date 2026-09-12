@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Audit Log Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('audit-logs.index') }}">Audit Trail</a></li>
                <li class="breadcrumb-item">Settings</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <a href="{{ route('audit-logs.index') }}" class="btn btn-light-brand">
                    <i class="feather-arrow-left me-2"></i>
                    <span>Back to Audit Trail</span>
                </a>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
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
        <form action="{{ route('audit-logs.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-archive me-2"></i>Archiving</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="archive_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="archive_enabled" name="archive_enabled" value="1" {{ old('archive_enabled', $settings->archive_enabled) ? 'checked' : '' }}>
                        <label class="form-check-label" for="archive_enabled">
                            Automatically archive old activity (daily, 03:30)
                        </label>
                    </div>

                    <label for="retention_days" class="form-label">Keep activity in the live table for (days)</label>
                    <input type="number" min="1" max="3650" class="form-control @error('retention_days') is-invalid @enderror" id="retention_days" name="retention_days" value="{{ old('retention_days', $settings->retention_days) }}" style="max-width: 200px;">
                    @error('retention_days')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    <div class="fs-12 text-muted mt-2">
                        Activity older than this moves out of the live table into an archive table — it stays fully
                        searchable on this page, it just no longer counts toward the live table's size. Nothing is
                        ever deleted.
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
