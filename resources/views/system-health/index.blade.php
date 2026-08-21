@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">System Health</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">System Health</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('system-health.index') }}" class="btn btn-light-brand">
                        <i class="feather-refresh-cw me-2"></i>
                        <span>Refresh</span>
                    </a>
                    @if($telescope['enabled'])
                        <a href="{{ url('/telescope') }}" target="_blank" class="btn btn-primary">
                            <i class="feather-activity me-2"></i>
                            <span>Open Telescope</span>
                        </a>
                    @endif
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
            <!-- Database -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-{{ $db['ok'] ? 'success' : 'danger' }} text-{{ $db['ok'] ? 'success' : 'danger' }}">
                                    <i class="feather-database fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Database</span>
                                    <span class="fs-16 fw-bolder d-block">
                                        <span class="badge bg-soft-{{ $db['ok'] ? 'success' : 'danger' }} text-{{ $db['ok'] ? 'success' : 'danger' }}">
                                            {{ $db['ok'] ? 'Connected' : 'Down' }}
                                        </span>
                                    </span>
                                    <span class="fs-12 text-muted">{{ $db['connection'] }} &middot; {{ $db['latency_ms'] }}ms</span>
                                </div>
                            </div>
                        </div>
                        @if(!$db['ok'])
                            <div class="fs-12 text-danger mt-2">{{ $db['error'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Queue -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-{{ $queue['ok'] ? 'success' : 'danger' }} text-{{ $queue['ok'] ? 'success' : 'danger' }}">
                                    <i class="feather-list fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Queue</span>
                                    <span class="fs-16 fw-bolder d-block">
                                        <span class="badge bg-soft-{{ $queue['ok'] ? 'success' : 'danger' }} text-{{ $queue['ok'] ? 'success' : 'danger' }}">
                                            {{ $queue['ok'] ? 'Reachable' : 'Down' }}
                                        </span>
                                    </span>
                                    <span class="fs-12 text-muted">{{ $queue['connection'] }} &middot; {{ $pendingJobsCount }} pending</span>
                                </div>
                            </div>
                        </div>
                        @if(!$queue['ok'])
                            <div class="fs-12 text-danger mt-2">{{ $queue['error'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Failed Jobs -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-{{ $failedJobsCount > 0 ? 'danger' : 'success' }} text-{{ $failedJobsCount > 0 ? 'danger' : 'success' }}">
                                    <i class="feather-alert-triangle fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Failed Jobs</span>
                                    <span class="fs-16 fw-bolder d-block">{{ $failedJobsCount }}</span>
                                </div>
                            </div>
                            <a href="#failed-jobs" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                <i class="feather-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs -->
            <div class="col-xxl-3 col-md-6">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-text avatar-xl rounded bg-soft-info text-info">
                                    <i class="feather-file-text fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted fw-medium d-block">Logs ({{ $logInfo['driver'] ?? 'n/a' }})</span>
                                    <span class="fs-16 fw-bolder d-block">{{ $logInfo['total_size_mb'] }} MB</span>
                                    <span class="fs-12 text-muted">
                                        retention: {{ $logInfo['retention_days'] ?? 'n/a' }}d
                                        @if($logInfo['latest_modified'])
                                            &middot; updated {{ $logInfo['latest_modified']->diffForHumans() }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Integrations -->
    <div class="col-12">
        <div class="row">
            <div class="col-xxl-3 col-md-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <span class="fw-medium">Telescope</span>
                        <span class="badge bg-soft-{{ $telescope['enabled'] ? 'success' : 'secondary' }} text-{{ $telescope['enabled'] ? 'success' : 'secondary' }}">
                            {{ $telescope['enabled'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <span class="fw-medium">Sentry</span>
                        <span class="badge bg-soft-{{ $sentry['configured'] ? 'success' : 'secondary' }} text-{{ $sentry['configured'] ? 'success' : 'secondary' }}">
                            {{ $sentry['configured'] ? 'Configured' : 'Not configured' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <span class="fw-medium">Environment</span>
                        <span class="badge bg-soft-{{ app()->environment('production') ? 'danger' : 'warning' }} text-{{ app()->environment('production') ? 'danger' : 'warning' }}">
                            {{ app()->environment() }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xxl-3 col-md-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <span class="fw-medium">Debug Mode</span>
                        <span class="badge bg-soft-{{ config('app.debug') ? 'danger' : 'success' }} text-{{ config('app.debug') ? 'danger' : 'success' }}">
                            {{ config('app.debug') ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Failed Jobs Table -->
    <div class="col-12" id="failed-jobs">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-alert-triangle me-2"></i>Failed Jobs</h5>
                @if($failedJobsCount > 0)
                    <form action="{{ route('system-health.failed-jobs.clear') }}" method="POST" onsubmit="return confirm('Clear ALL failed jobs? This cannot be undone.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light-danger">
                            <i class="feather-trash-2 me-2"></i>Clear All
                        </button>
                    </form>
                @endif
            </div>
            <div class="card-body pb-0">
                <!-- Bulk Actions Bar -->
                <div id="failedJobsBulkBar" class="d-none align-items-center justify-content-between bg-light border rounded p-2 mb-3">
                    <div class="d-flex align-items-center">
                        <span id="failedJobsSelectedCount" class="text-muted me-3">0 jobs selected</span>
                        <button type="button" id="failedJobsClearSelection" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="feather-x me-1"></i> Clear
                        </button>
                    </div>
                    <div class="hstack gap-2">
                        <button type="button" id="failedJobsBulkRetryBtn" class="btn btn-sm btn-primary">
                            <i class="feather-refresh-cw me-1"></i> Retry Selected
                        </button>
                        <button type="button" id="failedJobsBulkDeleteBtn" class="btn btn-sm btn-danger">
                            <i class="feather-trash-2 me-1"></i> Delete Selected
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="failedJobsTable">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width: 40px;">
                                    <div class="custom-control custom-checkbox ms-1">
                                        <input type="checkbox" class="custom-control-input" id="failedJobsSelectAll" title="Select all on this page">
                                        <label for="failedJobsSelectAll" class="custom-control-label"></label>
                                    </div>
                                </th>
                                @php
                                    $fjSort = fn ($col) => request()->fullUrlWithQuery(['sort_by' => $col, 'sort_order' => ($sortBy === $col && $sortOrder === 'asc') ? 'desc' : 'asc']);
                                    $fjArrow = function ($col) use ($sortBy, $sortOrder) {
                                        if ($sortBy !== $col) return '<i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>';
                                        return $sortOrder === 'asc' ? '<i class="feather-arrow-up fs-12"></i>' : '<i class="feather-arrow-down fs-12"></i>';
                                    };
                                @endphp
                                <th>
                                    <a href="{{ $fjSort('job') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Job <span class="ms-1">{!! $fjArrow('job') !!}</span>
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ $fjSort('queue') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Queue <span class="ms-1">{!! $fjArrow('queue') !!}</span>
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ $fjSort('failed_at') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Failed At <span class="ms-1">{!! $fjArrow('failed_at') !!}</span>
                                    </a>
                                </th>
                                <th>Exception</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($failedJobs as $job)
                                <tr>
                                    <td class="ps-3">
                                        <div class="custom-control custom-checkbox ms-1">
                                            <input type="checkbox" class="custom-control-input failed-job-checkbox" id="fj-{{ $job->uuid }}" value="{{ $job->uuid }}">
                                            <label for="fj-{{ $job->uuid }}" class="custom-control-label"></label>
                                        </div>
                                    </td>
                                    <td><span class="fw-semibold">{{ $job->job_class }}</span></td>
                                    <td>{{ $job->queue }}</td>
                                    <td class="fs-12 text-muted">{{ \Carbon\Carbon::parse($job->failed_at)->format('d M, Y H:i') }}</td>
                                    <td class="fs-12 text-danger" style="max-width: 420px;">
                                        <span class="d-inline-block text-truncate" style="max-width: 420px;" data-bs-toggle="tooltip" title="{{ $job->exception_excerpt }}">
                                            {{ $job->exception_excerpt }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="hstack gap-2 justify-content-end">
                                            <form action="{{ route('system-health.failed-jobs.retry', $job->uuid) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="avatar-text avatar-md text-primary border-0 bg-transparent" data-bs-toggle="tooltip" title="Retry">
                                                    <i class="feather-refresh-cw"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('system-health.failed-jobs.delete', $job->uuid) }}" method="POST" onsubmit="return confirm('Delete this failed job record?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="avatar-text avatar-md text-danger border-0 bg-transparent" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No failed jobs. Queue is healthy.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($failedJobs->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $failedJobs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    <!-- Scheduled Tasks -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-clock me-2"></i>Scheduled Tasks</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @php
                                    $stSort = fn ($col) => request()->fullUrlWithQuery(['schedule_sort_by' => $col, 'schedule_sort_order' => ($scheduleSortBy === $col && $scheduleSortOrder === 'asc') ? 'desc' : 'asc']);
                                    $stArrow = function ($col) use ($scheduleSortBy, $scheduleSortOrder) {
                                        if ($scheduleSortBy !== $col) return '<i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>';
                                        return $scheduleSortOrder === 'asc' ? '<i class="feather-arrow-up fs-12"></i>' : '<i class="feather-arrow-down fs-12"></i>';
                                    };
                                @endphp
                                <th class="ps-3">
                                    <a href="{{ $stSort('command') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Task <span class="ms-1">{!! $stArrow('command') !!}</span>
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ $stSort('expression') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Cron Expression <span class="ms-1">{!! $stArrow('expression') !!}</span>
                                    </a>
                                </th>
                                <th>
                                    <a href="{{ $stSort('frequency') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Runs <span class="ms-1">{!! $stArrow('frequency') !!}</span>
                                    </a>
                                </th>
                                <th class="pe-3">
                                    <a href="{{ $stSort('next_due') }}" class="d-flex align-items-center text-dark text-decoration-none">
                                        Next Due <span class="ms-1">{!! $stArrow('next_due') !!}</span>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($scheduledTasks as $task)
                                <tr>
                                    <td class="ps-3 fs-13">{{ $task['command'] }}</td>
                                    <td class="fs-12 text-muted">{{ $task['expression'] }}</td>
                                    <td class="fs-12 text-muted">{{ $task['frequency'] }}</td>
                                    <td class="pe-3 fs-12 text-muted">
                                        @if($task['next_due'])
                                            <span class="next-due-countdown" data-next-due="{{ \Carbon\Carbon::parse($task['next_due'])->toIso8601String() }}" data-bs-toggle="tooltip" title="{{ $task['next_due'] }}">
                                                {{ \Carbon\Carbon::parse($task['next_due'])->diffForHumans() }}
                                            </span>
                                        @else
                                            n/a
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No scheduled tasks registered.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        var selectedIds = [];
        var csrfToken = '{{ csrf_token() }}';
        var bulkRetryUrl = '{{ route('system-health.failed-jobs.bulk-retry') }}';
        var bulkDeleteUrl = '{{ route('system-health.failed-jobs.bulk-delete') }}';

        function updateSelectedIds() {
            selectedIds = [];
            $('.failed-job-checkbox:checked').each(function () {
                selectedIds.push($(this).val());
            });
        }

        function updateSelectAllState() {
            var total = $('.failed-job-checkbox').length;
            var checked = $('.failed-job-checkbox:checked').length;

            if (checked === 0) {
                $('#failedJobsSelectAll').prop('checked', false).prop('indeterminate', false);
            } else if (checked === total) {
                $('#failedJobsSelectAll').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#failedJobsSelectAll').prop('checked', false).prop('indeterminate', true);
            }
        }

        function updateBulkBar() {
            if (selectedIds.length > 0) {
                $('#failedJobsBulkBar').removeClass('d-none').addClass('d-flex');
                $('#failedJobsSelectedCount').text(selectedIds.length + ' job(s) selected');
            } else {
                $('#failedJobsBulkBar').removeClass('d-flex').addClass('d-none');
            }
        }

        $(document).on('change', '#failedJobsSelectAll', function () {
            $('.failed-job-checkbox').prop('checked', $(this).prop('checked'));
            updateSelectedIds();
            updateBulkBar();
        });

        $(document).on('change', '.failed-job-checkbox', function () {
            updateSelectedIds();
            updateSelectAllState();
            updateBulkBar();
        });

        $(document).on('click', '#failedJobsClearSelection', function () {
            $('#failedJobsSelectAll').prop('checked', false).prop('indeterminate', false);
            $('.failed-job-checkbox').prop('checked', false);
            selectedIds = [];
            updateBulkBar();
        });

        function submitBulkAction(url, confirmMsg, successVerb) {
            if (selectedIds.length === 0) {
                alert('Please select at least one job.');
                return;
            }

            if (!confirm(confirmMsg.replace('%d', selectedIds.length))) {
                return;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: { _token: csrfToken, ids: selectedIds },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || ('Failed to ' + successVerb + ' selected jobs.'));
                    }
                },
                error: function (xhr) {
                    alert(xhr.responseJSON?.message || ('An error occurred while trying to ' + successVerb + ' selected jobs.'));
                }
            });
        }

        $(document).on('click', '#failedJobsBulkRetryBtn', function () {
            submitBulkAction(bulkRetryUrl, 'Retry %d selected job(s)?', 'retry');
        });

        $(document).on('click', '#failedJobsBulkDeleteBtn', function () {
            submitBulkAction(bulkDeleteUrl, 'Delete %d selected job(s)? This cannot be undone.', 'delete');
        });

        function formatCountdown(ms) {
            if (ms <= 0) {
                return 'due now';
            }

            var totalSeconds = Math.floor(ms / 1000);
            var h = Math.floor(totalSeconds / 3600);
            var m = Math.floor((totalSeconds % 3600) / 60);
            var s = totalSeconds % 60;

            var parts = [];
            if (h > 0) parts.push(h + 'h');
            if (h > 0 || m > 0) parts.push(m + 'm');
            parts.push(s + 's');

            return 'in ' + parts.join(' ');
        }

        function tickCountdowns() {
            var now = Date.now();
            $('.next-due-countdown').each(function () {
                var target = new Date($(this).data('next-due')).getTime();
                $(this).text(formatCountdown(target - now));
            });
        }

        if ($('.next-due-countdown').length) {
            tickCountdowns();
            setInterval(tickCountdowns, 1000);
        }
    });
</script>
@endpush
