{{-- Buyer conversations linked to this order (from the Messages inbox). --}}
@can('view messages')
    @php
        $statusBadge = [
            'pending' => ['bg-soft-warning text-warning', 'Needs reply'],
            'awaiting_buyer' => ['bg-soft-primary text-primary', 'Awaiting buyer'],
            'resolved' => ['bg-soft-success text-success', 'Resolved'],
        ];
    @endphp
    <div class="card mt-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title"><i class="feather-inbox me-2"></i>Conversations ({{ $messageThreads->count() }})</h5>
        </div>
        <div class="card-body">
            @forelse($messageThreads as $thread)
                @php($badge = $statusBadge[$thread->status] ?? ['bg-soft-secondary text-secondary', ucfirst(str_replace('_', ' ', $thread->status))])
                <div class="{{ $loop->last ? '' : 'border-bottom pb-3 mb-3' }}">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <span class="fw-semibold">{{ $thread->buyer_name ?: ($thread->buyer_username ?: 'Buyer') }}</span>
                        <span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
                    </div>
                    <div class="fs-12 text-muted mb-2">
                        {{ $thread->salesChannel?->name }}
                        @if($thread->last_message_at) &middot; {{ $thread->last_message_at->diffForHumans() }} @endif
                        @if($thread->unread_count > 0) &middot; <span class="text-danger">{{ $thread->unread_count }} unread</span> @endif
                    </div>
                    @foreach($thread->recentMessages as $m)
                        <div class="fs-13 mb-1 {{ $m->direction === 'inbound' ? '' : 'text-muted' }}">
                            <strong>{{ $m->direction === 'inbound' ? 'Buyer' : ($m->is_auto_reply ? 'Automatic reply' : 'You') }}:</strong>
                            {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $m->body), 160) }}
                        </div>
                    @endforeach
                    <a href="{{ route('messages.index', ['thread' => $thread->id]) }}" class="btn btn-sm btn-light-brand mt-2">
                        <i class="feather-external-link me-1"></i>Open in inbox
                    </a>
                </div>
            @empty
                <p class="text-muted mb-0 fs-13">No buyer conversations are linked to this order.</p>
            @endforelse
        </div>
    </div>
@endcan
