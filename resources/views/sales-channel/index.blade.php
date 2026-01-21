@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Sales Channels</h1>
                    @can('add purchases')
                        <a href="{{ route('sales-channels.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Sales Channel</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Sales Channels</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Name</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales_channels as $sales_channel)
                    <tr>
                        <td>{{ $sales_channel->id }}</td>
                        <td>
                            {{ $sales_channel->name }}
                            <!-- Import Progress Container -->
                            <div class="import-progress-container mt-2" id="import-progress-{{ $sales_channel->id }}" style="display: none;">
                                <div class="d-flex align-items-center mb-1">
                                    <small class="text-muted mr-2">
                                        <i class="fas fa-sync fa-spin mr-1"></i>
                                        <span class="import-status-text">Importing...</span>
                                    </small>
                                    <small class="text-muted ml-auto import-progress-details"></small>
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
                                    <small class="text-success mr-2"><i class="fas fa-plus-circle"></i> <span class="inserted-count">0</span> new</small>
                                    <small class="text-info mr-2"><i class="fas fa-edit"></i> <span class="updated-count">0</span> updated</small>
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> <span class="failed-count">0</span> failed</small>
                                </div>
                            </div>
                            <!-- Import Complete Message -->
                            <div class="import-complete-container mt-2" id="import-complete-{{ $sales_channel->id }}" style="display: none;">
                                <div class="alert alert-success alert-sm py-1 px-2 mb-0" style="font-size: 0.85rem;">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <span class="complete-message">Import completed!</span>
                                    <button type="button" class="close ml-2" style="font-size: 1rem;" onclick="hideCompleteMessage({{ $sales_channel->id }})">
                                        <span>&times;</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($sales_channel->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('ebay.listings-all.active', $sales_channel->id) }}"
                                   class="btn btn-success btn-sm import-btn"
                                   data-channel-id="{{ $sales_channel->id }}"
                                   id="import-btn-{{ $sales_channel->id }}">
                                    Import Products
                                </a>
                                @can('edit sales_channels')
                                    <a href="{{ route('sales-channels.edit', $sales_channel->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('delete sales_channels')
                                    <form action="{{ route('sales-channels.destroy', $sales_channel->id) }}" method="POST" id="sales-channel-{{ $sales_channel->id }}-delete-form">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <a href="javascript:void(0)" data-id="{{ $sales_channel->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $sales_channels->links('pagination::bootstrap-5') }}
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

            // Poll every 2 seconds
            pollingIntervals[channelId] = setInterval(function() {
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

        function stopPolling(channelId) {
            if (pollingIntervals[channelId]) {
                clearInterval(pollingIntervals[channelId]);
                delete pollingIntervals[channelId];
            }
        }

        function showProgress(channelId, data) {
            const container = $('#import-progress-' + channelId);
            const btn = $('#import-btn-' + channelId);

            container.show();
            btn.addClass('disabled').text('Importing...');

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
            message.html('<i class="fas fa-times-circle mr-1"></i> Import failed. Check logs for details.');
            container.show();
        }

        function hideCompleteMessage(channelId) {
            $('#import-complete-' + channelId).hide();
        }

        function enableImportButton(channelId) {
            const btn = $('#import-btn-' + channelId);
            btn.removeClass('disabled').text('Import Products');
        }
    </script>
@endpush
