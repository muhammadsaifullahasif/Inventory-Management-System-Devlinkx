{{-- Messages inbox sync health, one row per store. Data comes from MessageHealth. --}}
@php
    $badge = [
        'ok' => ['bg-soft-success text-success', 'Syncing'],
        'never' => ['bg-soft-secondary text-secondary', 'Not synced yet'],
        'stale' => ['bg-soft-warning text-warning', 'Behind'],
        'failing' => ['bg-soft-danger text-danger', 'Failing'],
        'reauthorize' => ['bg-soft-danger text-danger', 'Re-authorize'],
    ];
@endphp
<div class="col-12">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title"><i class="feather-message-square me-2"></i>Messages Sync</h5>
            <div class="text-muted fs-12">
                {{ $messageWorkload['threads'] }} conversations &middot;
                {{ $messageWorkload['needing_reply'] }} waiting for a reply &middot;
                {{ $messageWorkload['failed_sends'] }} {{ \Illuminate\Support\Str::plural('reply', $messageWorkload['failed_sends']) }} not delivered
            </div>
        </div>
        <div class="card-body p-0">
            @if($messageStores->isEmpty())
                <p class="text-muted p-4 mb-0">No store has messaging switched on.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Status</th>
                                <th>Last good sync</th>
                                <th>Auto-reply</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($messageStores as $s)
                                <tr>
                                    <td class="fw-semibold">{{ $s['name'] }}</td>
                                    <td><span class="badge {{ $badge[$s['status']][0] }}">{{ $badge[$s['status']][1] }}</span></td>
                                    <td>{{ $s['last_synced_at']?->diffForHumans() ?? 'never' }}</td>
                                    <td>{{ $s['auto_reply_enabled'] ? 'On' : 'Off' }}</td>
                                    <td class="text-muted fs-12">
                                        {{ \Illuminate\Support\Str::limit((string) $s['reason'], 200) }}
                                        @if($s['reason'] && $s['last_error_at'])
                                            <span class="d-block">since {{ $s['last_error_at']->diffForHumans() }}</span>
                                        @endif
                                        @if($s['status'] === 'reauthorize')
                                            @can('edit sales-channels')
                                                <a href="{{ route('sales-channels.edit', $s['id']) }}">Open the store</a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

