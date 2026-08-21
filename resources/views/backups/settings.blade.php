@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Backup Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('backups.index') }}">Backups</a></li>
                <li class="breadcrumb-item">Settings</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <a href="{{ route('backups.index') }}" class="btn btn-light-brand">
                    <i class="feather-arrow-left me-2"></i>
                    <span>Back to Backups</span>
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
        <form action="{{ route('backups.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-toggle-right me-2"></i>Schedule</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input type="hidden" name="schedule_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="schedule_enabled" name="schedule_enabled" value="1" {{ old('schedule_enabled', $settings->schedule_enabled) ? 'checked' : '' }}>
                        <label class="form-check-label" for="schedule_enabled">
                            Run scheduled backups (<code>backup:run</code> daily 02:00, <code>backup:monitor</code> daily 02:30, <code>backup:clean</code> weekly Monday 03:00)
                        </label>
                    </div>
                    <div class="fs-12 text-muted mt-2">
                        Turning this off does not delete existing backups — it only skips the next scheduled runs. Manual "Run Backup Now" on the Backups page still works either way.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-mail me-2"></i>Notifications</h5>
                </div>
                <div class="card-body">
                    <label for="notification_email" class="form-label">Notification email</label>
                    <input type="email" class="form-control @error('notification_email') is-invalid @enderror" id="notification_email" name="notification_email" value="{{ old('notification_email', $settings->notification_email) }}" placeholder="admin@example.com">
                    <div class="fs-12 text-muted mt-1">
                        Used only if spatie's mail notification channel is enabled later. Failure alerting itself already happens automatically via <code>Log::critical</code> + Sentry — this field does not affect that.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-archive me-2"></i>Retention</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="keep_daily_backups_for_days" class="form-label">Keep daily backups (days)</label>
                            <input type="number" min="1" max="365" class="form-control @error('keep_daily_backups_for_days') is-invalid @enderror" id="keep_daily_backups_for_days" name="keep_daily_backups_for_days" value="{{ old('keep_daily_backups_for_days', $settings->keep_daily_backups_for_days) }}">
                        </div>
                        <div class="col-md-4">
                            <label for="keep_weekly_backups_for_weeks" class="form-label">Keep weekly backups (weeks)</label>
                            <input type="number" min="0" max="104" class="form-control @error('keep_weekly_backups_for_weeks') is-invalid @enderror" id="keep_weekly_backups_for_weeks" name="keep_weekly_backups_for_weeks" value="{{ old('keep_weekly_backups_for_weeks', $settings->keep_weekly_backups_for_weeks) }}">
                        </div>
                        <div class="col-md-4">
                            <label for="keep_monthly_backups_for_months" class="form-label">Keep monthly backups (months)</label>
                            <input type="number" min="0" max="60" class="form-control @error('keep_monthly_backups_for_months') is-invalid @enderror" id="keep_monthly_backups_for_months" name="keep_monthly_backups_for_months" value="{{ old('keep_monthly_backups_for_months', $settings->keep_monthly_backups_for_months) }}">
                        </div>
                    </div>
                    <div class="fs-12 text-muted mt-2">
                        Applied on the next <code>backup:clean</code> run (weekly, Monday 03:00). Default: 7 / 4 / 3.
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
