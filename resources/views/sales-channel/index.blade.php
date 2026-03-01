@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Sales Channels</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Sales Channels</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add purchases')
                    <a href="{{ route('sales-channels.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Sales Channel</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title"><i class="feather-filter me-2"></i>Filters</h5>
                <a href="javascript:void(0);" class="avatar-text avatar-md text-primary" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="feather-minus toggle-icon"></i>
                </a>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body py-3">
                    <form action="{{ route('sales-channels.index') }}" method="GET">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Channel name..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="connected" {{ request('status') == 'connected' ? 'selected' : '' }}>Connected</option>
                                    <option value="disconnected" {{ request('status') == 'disconnected' ? 'selected' : '' }}>Disconnected</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="feather-search me-2"></i>Filter
                                </button>
                                <a href="{{ route('sales-channels.index') }}" class="btn btn-light-brand btn-sm">
                                    <i class="feather-x me-2"></i>Clear
                                </a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end justify-content-end">
                                <span class="text-muted fs-12">{{ $sales_channels->total() }} results</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Channels Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Created at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sales_channels as $sales_channel)
                                <tr>
                                    <td>{{ $sales_channel->id }}</td>
                                    <td>
                                        <span class="fw-semibold">{{ $sales_channel->name }}</span>
                                        <!-- Import Progress Container -->
                                        <div class="import-progress-container mt-2" id="import-progress-{{ $sales_channel->id }}" style="display: none;">
                                            <div class="d-flex align-items-center mb-1">
                                                <small class="text-muted me-2">
                                                    <div class="spinner-border spinner-border-sm me-1" role="status"></div>
                                                    <span class="import-status-text">Importing...</span>
                                                </small>
                                                <small class="text-muted ms-auto import-progress-details"></small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                     role="progressbar"
                                                     style="width: 0%;"
                                                     aria-valuenow="0"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                            <div class="import-stats mt-1" style="display: none;">
                                                <small class="text-success me-2"><i class="feather-plus-circle"></i> <span class="inserted-count">0</span> new</small>
                                                <small class="text-info me-2"><i class="feather-edit"></i> <span class="updated-count">0</span> updated</small>
                                                <small class="text-danger"><i class="feather-alert-circle"></i> <span class="failed-count">0</span> failed</small>
                                            </div>
                                        </div>
                                        <!-- Import Complete Message -->
                                        <div class="import-complete-container mt-2" id="import-complete-{{ $sales_channel->id }}" style="display: none;">
                                            <div class="alert alert-success py-1 px-2 mb-0 d-flex align-items-center justify-content-between" style="font-size: 0.85rem;">
                                                <span>
                                                    <i class="feather-check-circle me-1"></i>
                                                    <span class="complete-message">Import completed!</span>
                                                </span>
                                                <button type="button" class="btn-close" style="font-size: 0.75rem;" onclick="hideCompleteMessage({{ $sales_channel->id }})"></button>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="fs-12 text-muted">{{ \Carbon\Carbon::parse($sales_channel->created_at)->format('d M, Y') }}</span></td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end flex-wrap">
                                            <a href="{{ route('ebay.listings-all.active', $sales_channel->id) }}"
                                               class="btn btn-sm btn-success import-btn"
                                               data-channel-id="{{ $sales_channel->id }}"
                                               id="import-btn-{{ $sales_channel->id }}">
                                                <i class="feather-download me-1"></i>Import
                                            </a>
                                            <a href="{{ route('ebay.listings.sync', $sales_channel->id) }}"
                                               class="btn btn-sm btn-warning sync-listings-btn"
                                               data-channel-id="{{ $sales_channel->id }}"
                                               id="sync-btn-{{ $sales_channel->id }}"
                                               title="Sync new listings only">
                                                <i class="feather-refresh-cw me-1"></i>Sync
                                            </a>
                                            <a href="{{ route('ebay.orders.sync', $sales_channel->id) }}"
                                               class="btn btn-sm btn-info"
                                               title="Sync orders from eBay">
                                                <i class="feather-shopping-cart me-1"></i>Orders
                                            </a>
                                            <button type="button"
                                               class="btn btn-sm btn-secondary subscribe-events-btn"
                                               data-channel-id="{{ $sales_channel->id }}"
                                               id="subscribe-btn-{{ $sales_channel->id }}"
                                               title="Subscribe to eBay notifications (orders, returns, cancellations)">
                                                <i class="feather-bell me-1"></i>Subscribe
                                            </button>
                                            @can('edit sales_channels')
                                                <a href="{{ route('sales-channels.edit', $sales_channel->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete sales_channels')
                                                <form action="{{ route('sales-channels.destroy', $sales_channel->id) }}" method="POST" id="sales-channel-{{ $sales_channel->id }}-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                                <a href="javascript:void(0)" data-id="{{ $sales_channel->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                                                    <i class="feather-trash-2"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No sales channels found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div>
                    @include('partials.per-page-dropdown', ['perPage' => $perPage])
                </div>
                <div>
                    {{ $sales_channels->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Track active polling intervals
        const pollingIntervals = {};
        const salesChannelIds = @json($sales_channels->pluck('id'));

        $(document).ready(function(){
            // Delete button handler
            $(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
                if (confirm('Are you sure to delete the record?')) {
                    $('#sales-channel-' + id + '-delete-form').submit();
                } else {
                    e.preventDefault();
                    return false;
                }
            });

            // Check for active imports on page load
            salesChannelIds.forEach(function(channelId) {
                checkImportStatus(channelId);
            });
        });

        function checkImportStatus(channelId) {
            $.ajax({
                url: '{{ url("/ebay/import-logs/latest") }}/' + channelId,
                method: 'GET',
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;

                        // If there's an active import (pending or processing), show progress and start polling
                        if (data.status === 'pending' || data.status === 'processing') {
                            showProgress(channelId, data);
                            startPolling(channelId);
                        } else if (data.status === 'completed') {
                            // Check if completed recently (within last 30 seconds)
                            const completedAt = new Date(data.completed_at);
                            const now = new Date();
                            const diffSeconds = (now - completedAt) / 1000;

                            if (diffSeconds < 30) {
                                showCompleteMessage(channelId, data);
                            }
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Failed to check import status for channel ' + channelId);
                }
            });
        }

        function startPolling(channelId) {
            // Clear existing interval if any
            if (pollingIntervals[channelId]) {
                clearInterval(pollingIntervals[channelId]);
            }

            // Start processing queue jobs
            processQueueJob();

            // Poll every 2 seconds
            pollingIntervals[channelId] = setInterval(function() {
                // Trigger queue processing on each poll
                processQueueJob();

                $.ajax({
                    url: '{{ url("/ebay/import-logs/latest") }}/' + channelId,
                    method: 'GET',
                    success: function(response) {
                        if (response.success && response.data) {
                            const data = response.data;

                            if (data.status === 'pending' || data.status === 'processing') {
                                updateProgress(channelId, data);
                            } else if (data.status === 'completed') {
                                stopPolling(channelId);
                                hideProgress(channelId);
                                showCompleteMessage(channelId, data);
                                enableImportButton(channelId);
                            } else if (data.status === 'failed') {
                                stopPolling(channelId);
                                hideProgress(channelId);
                                showFailedMessage(channelId, data);
                                enableImportButton(channelId);
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Polling error for channel ' + channelId);
                    }
                });
            }, 2000);
        }

        // Process one queue job (called repeatedly during polling)
        let isProcessingQueue = false;
        function processQueueJob() {
            if (isProcessingQueue) return; // Prevent concurrent calls
            isProcessingQueue = true;

            $.ajax({
                url: '{{ url("/run-queue") }}',
                method: 'GET',
                timeout: 60000, // 60 second timeout
                complete: function() {
                    isProcessingQueue = false;
                }
            });
        }

        function stopPolling(channelId) {
            if (pollingIntervals[channelId]) {
                clearInterval(pollingIntervals[channelId]);
                delete pollingIntervals[channelId];
            }
        }

        function showProgress(channelId, data) {
            const container = $('#import-progress-' + channelId);
            const importBtn = $('#import-btn-' + channelId);
            const syncBtn = $('#sync-btn-' + channelId);

            container.show();
            importBtn.addClass('disabled').html('<div class="spinner-border spinner-border-sm me-1"></div>Importing...');
            syncBtn.addClass('disabled').html('<div class="spinner-border spinner-border-sm me-1"></div>Syncing...');

            updateProgress(channelId, data);
        }

        function updateProgress(channelId, data) {
            const container = $('#import-progress-' + channelId);
            const progressBar = container.find('.progress-bar');
            const statusText = container.find('.import-status-text');
            const detailsText = container.find('.import-progress-details');
            const statsContainer = container.find('.import-stats');

            const percentage = data.progress_percentage || 0;

            progressBar
                .css('width', percentage + '%')
                .attr('aria-valuenow', percentage);

            // Update status text
            if (data.status === 'pending') {
                statusText.text('Preparing import...');
            } else {
                statusText.text('Importing... ' + percentage.toFixed(1) + '%');
            }

            // Update details
            detailsText.text('Batch ' + data.completed_batches + ' of ' + data.total_batches);

            // Update stats
            if (data.items_inserted > 0 || data.items_updated > 0 || data.items_failed > 0) {
                statsContainer.show();
                container.find('.inserted-count').text(data.items_inserted);
                container.find('.updated-count').text(data.items_updated);
                container.find('.failed-count').text(data.items_failed);
            }
        }

        function hideProgress(channelId) {
            $('#import-progress-' + channelId).hide();
        }

        function showCompleteMessage(channelId, data) {
            const container = $('#import-complete-' + channelId);
            const message = container.find('.complete-message');

            let text = 'Import completed! ';
            text += data.items_inserted + ' new, ';
            text += data.items_updated + ' updated';
            if (data.items_failed > 0) {
                text += ', ' + data.items_failed + ' failed';
            }

            message.text(text);
            container.show();

            // Auto-hide after 10 seconds
            setTimeout(function() {
                hideCompleteMessage(channelId);
            }, 10000);
        }

        function showFailedMessage(channelId, data) {
            const container = $('#import-complete-' + channelId);
            const alert = container.find('.alert');
            const message = container.find('.complete-message');

            alert.removeClass('alert-success').addClass('alert-danger');
            message.html('<i class="feather-x-circle me-1"></i> Import failed. Check logs for details.');
            container.show();
        }

        function hideCompleteMessage(channelId) {
            $('#import-complete-' + channelId).hide();
        }

        function enableImportButton(channelId) {
            const btn = $('#import-btn-' + channelId);
            btn.removeClass('disabled').html('<i class="feather-download me-1"></i>Import');

            const syncBtn = $('#sync-btn-' + channelId);
            syncBtn.removeClass('disabled').html('<i class="feather-refresh-cw me-1"></i>Sync');
        }

        // Handle sync listings button click
        $(document).on('click', '.sync-listings-btn', function(e) {
            const channelId = $(this).data('channel-id');
            const btn = $(this);

            // Show loading state
            btn.addClass('disabled').html('<div class="spinner-border spinner-border-sm me-1"></div>Syncing...');
            $('#import-btn-' + channelId).addClass('disabled');
        });

        // Handle subscribe events button click
        $(document).on('click', '.subscribe-events-btn', function(e) {
            e.preventDefault();
            const channelId = $(this).data('channel-id');
            const btn = $(this);

            // Show loading state
            btn.addClass('disabled').html('<div class="spinner-border spinner-border-sm me-1"></div>Subscribing...');

            $.ajax({
                url: '/api/ebay/notifications/' + channelId + '/subscribe-complete',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    if (response.success) {
                        // Show success
                        btn.removeClass('btn-secondary').addClass('btn-success');
                        btn.html('<i class="feather-check me-1"></i>Subscribed');

                        // Show success message
                        showToast('success', 'Successfully subscribed to eBay notifications!');

                        // Reset button after 3 seconds
                        setTimeout(function() {
                            btn.removeClass('btn-success disabled').addClass('btn-secondary');
                            btn.html('<i class="feather-bell me-1"></i>Subscribe');
                        }, 3000);
                    } else {
                        // Show error
                        btn.removeClass('disabled').html('<i class="feather-bell me-1"></i>Subscribe');
                        showToast('error', response.message || 'Failed to subscribe to notifications');
                    }
                },
                error: function(xhr) {
                    btn.removeClass('disabled').html('<i class="feather-bell me-1"></i>Subscribe');
                    const errorMsg = xhr.responseJSON?.message || 'Failed to subscribe to notifications';
                    showToast('error', errorMsg);
                }
            });
        });

        // Toast notification helper
        function showToast(type, message) {
            // Check if toastr is available
            if (typeof toastr !== 'undefined') {
                toastr[type](message);
            } else {
                // Fallback to alert
                alert(message);
            }
        }
    </script>
@endpush
