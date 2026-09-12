@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Activity Detail</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('audit-logs.index') }}">Audit Trail</a></li>
                <li class="breadcrumb-item">#{{ $log->id }}</li>
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
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-info me-2"></i>Summary</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr><th class="w-25">Event</th><td>{{ ucfirst(str_replace('_', ' ', $log->event)) }}</td></tr>
                        <tr><th>Date / Time</th><td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td></tr>
                        <tr><th>Model</th><td>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td></tr>
                        <tr>
                            <th>Performed By</th>
                            <td>
                                {{ $log->actor_label ?? '—' }}
                                @if($log->actor_type === 'user' && $log->actor_id)
                                    <a href="{{ route('audit-logs.index', ['actor_type' => 'user', 'actor_id' => $log->actor_id]) }}" class="ms-2 fs-12" title="View all activity by this user">
                                        <i class="feather-filter"></i> View all activity
                                    </a>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Actor Type</th><td>{{ ucfirst($log->actor_type) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-globe me-2"></i>Request Context</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr><th class="w-25">IP Address</th><td>{{ $log->ip_address ?? '—' }}</td></tr>
                        <tr><th>Browser / User Agent</th><td class="text-break">{{ $log->user_agent ?? '—' }}</td></tr>
                        <tr><th>URL</th><td class="text-break">{{ $log->url ?? '—' }}</td></tr>
                        <tr><th>Method</th><td>{{ $log->http_method ?? '—' }}</td></tr>
                        @php
                            $extraContext = collect($log->context ?? [])->except(['affected', 'affected_count'])->all();
                        @endphp
                        @if(!empty($extraContext))
                            <tr><th>Extra Context</th><td><pre class="mb-0">{{ json_encode($extraContext, JSON_PRETTY_PRINT) }}</pre></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(!empty($log->context['affected'] ?? null))
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title"><i class="feather-list me-2"></i>Affected Records</h5>
                    <span class="text-muted fs-12">{{ $log->context['affected_count'] ?? count($log->context['affected']) }} record(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Record</th>
                                    <th>Effect</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($log->context['affected'] as $item)
                                    <tr>
                                        <td>{{ $item['type'] ?? '—' }}</td>
                                        <td>{{ $item['label'] ?? ($item['id'] ?? '—') }}</td>
                                        <td>
                                            @php $effect = $item['effect'] ?? '—'; @endphp
                                            <span class="badge bg-light-{{ in_array($effect, ['sync_failed', 'failed', 'import_failed', 'link_failed', 'no_matching_listing']) ? 'danger' : 'primary' }} text-{{ in_array($effect, ['sync_failed', 'failed', 'import_failed', 'link_failed', 'no_matching_listing']) ? 'danger' : 'primary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $effect)) }}
                                            </span>
                                        </td>
                                        <td class="text-break fs-12">
                                            {{ json_encode(collect($item)->except(['type', 'id', 'label', 'effect'])->all()) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($log->old_values) || !empty($log->new_values))
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-git-commit me-2"></i>Changes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 200px;">Field</th>
                                    <th style="width: 300px;">Before</th>
                                    <th style="width: 300px;">After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $old = $log->old_values ?? [];
                                    $new = $log->new_values ?? [];
                                    $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
                                @endphp
                                @forelse ($fields as $field)
                                    <tr>
                                        <td><code>{{ $field }}</code></td>
                                        <td class="text-danger"><span style="white-space: normal; width: 300px; display: block;" class="fw-semibold">{{ array_key_exists($field, $old) ? (is_scalar($old[$field]) ? $old[$field] : json_encode($old[$field])) : '—' }}</span></td>
                                        <td class="text-success"><span style="white-space: normal; width: 300px; display: block;" class="fw-semibold">{{ array_key_exists($field, $new) ? (is_scalar($new[$field]) ? $new[$field] : json_encode($new[$field])) : '—' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">No field-level changes recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
