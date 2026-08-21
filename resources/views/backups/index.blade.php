@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Backups</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Backups</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('manage backup-settings')
                    <a href="{{ route('backups.settings.edit') }}" class="btn btn-light-brand">
                        <i class="feather-settings me-2"></i>
                        <span>Settings</span>
                    </a>
                    @endcan
                    <form action="{{ route('backups.monitor') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-light-brand">
                            <i class="feather-heart me-2"></i>
                            <span>Run Health Check</span>
                        </button>
                    </form>
                    @can('create backups')
                    <form action="{{ route('backups.run') }}" method="POST" onsubmit="return confirm('Queue a full DB + storage/app backup now?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-database me-2"></i>
                            <span>Run Backup Now</span>
                        </button>
                    </form>
                    @endcan
                </div>
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

    <!-- Status Cards -->
    <div class="col-12">
        <div class="row">
            <!-- Health -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-xl rounded bg-soft-{{ $healthy ? 'success' : 'danger' }} text-{{ $healthy ? 'success' : 'danger' }}">
                                <i class="feather-{{ $healthy ? 'shield' : 'alert-triangle' }} fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Backup Health</span>
                                <span class="fs-16 fw-bolder d-block">
                                    <span class="badge bg-soft-{{ $healthy ? 'success' : 'danger' }} text-{{ $healthy ? 'success' : 'danger' }}">
                                        {{ $healthy ? 'Healthy' : 'Unhealthy' }}
                                    </span>
                                </span>
                                <span class="fs-12 text-muted">disk: {{ $diskName }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Newest backup -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-xl rounded bg-soft-info text-info">
                                <i class="feather-clock fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Newest Backup</span>
                                <span class="fs-16 fw-bolder d-block">
                                    {{ $newest ? $newest['modified']->diffForHumans() : 'None yet' }}
                                </span>
                                <span class="fs-12 text-muted">
                                    {{ $newest ? $newest['modified']->format('d M, Y H:i') : 'Run a backup to get started' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Count -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-xl rounded bg-soft-primary text-primary">
                                <i class="feather-archive fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Backups Stored</span>
                                <span class="fs-16 fw-bolder d-block">{{ $files->count() }}</span>
                                <span class="fs-12 text-muted">{{ $backupSettings->keep_daily_backups_for_days }}d / {{ $backupSettings->keep_weekly_backups_for_weeks }}wk / {{ $backupSettings->keep_monthly_backups_for_months }}mo retained</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Size -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-xl rounded bg-soft-warning text-warning">
                                <i class="feather-hard-drive fs-4"></i>
                            </div>
                            <div>
                                <span class="text-muted fw-medium d-block">Total Size</span>
                                <span class="fs-16 fw-bolder d-block">{{ $totalSizeMb }} MB</span>
                                <span class="fs-12 text-muted">storage/app/backups</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-calendar me-2"></i>Schedule</h5>
                <span class="badge bg-soft-{{ $backupSettings->schedule_enabled ? 'success' : 'secondary' }} text-{{ $backupSettings->schedule_enabled ? 'success' : 'secondary' }}">
                    {{ $backupSettings->schedule_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Command</th>
                                <th>When</th>
                                <th class="pe-3">Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedule as $task)
                                <tr>
                                    <td class="ps-3 fs-13"><code>{{ $task['task'] }}</code></td>
                                    <td class="fs-12 text-muted">{{ $task['when'] }}</td>
                                    <td class="pe-3 fs-12 text-muted">{{ $task['purpose'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Backups list -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-archive me-2"></i>Stored Backups</h5>
                <span class="fs-12 text-muted">Restore runbook: <code>RESTORE.md</code> (repo root)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Filename</th>
                                <th>Created</th>
                                <th>Size</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($files as $file)
                                <tr>
                                    <td class="ps-3 fs-13">{{ $file['filename'] }}</td>
                                    <td class="fs-12 text-muted">{{ $file['modified']->format('d M, Y H:i') }} &middot; {{ $file['modified']->diffForHumans() }}</td>
                                    <td class="fs-12 text-muted">{{ $file['size_mb'] }} MB</td>
                                    <td class="text-end pe-3">
                                        <div class="hstack gap-2 justify-content-end">
                                            <a href="{{ route('backups.download', $file['filename']) }}" class="avatar-text avatar-md text-primary" data-bs-toggle="tooltip" title="Download">
                                                <i class="feather-download"></i>
                                            </a>
                                            @can('delete backups')
                                            <form action="{{ route('backups.destroy', $file['filename']) }}" method="POST" onsubmit="return confirm('Delete {{ $file['filename'] }}? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="avatar-text avatar-md text-danger border-0 bg-transparent" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No backups yet. Click "Run Backup Now" to create one.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
