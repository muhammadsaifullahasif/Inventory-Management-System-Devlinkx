@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Notifications</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Notifications</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @if($unreadCount > 0)
                        <form action="{{ route('notifications.mark-all-as-read') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-light-brand">
                                <i class="feather-check me-2"></i>
                                <span>Mark All as Read</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <ul class="nav nav-pills gap-2">
                    <li class="nav-item">
                        <a href="{{ route('notifications.index') }}" class="nav-link {{ $filter === 'all' ? 'active' : '' }}">
                            All
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="nav-link {{ $filter === 'unread' ? 'active' : '' }}">
                            Unread
                            @if($unreadCount > 0)
                                <span class="badge bg-danger ms-1">{{ $unreadCount }}</span>
                            @endif
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width: 40px"></th>
                                <th>Message</th>
                                <th>Received</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($notifications as $notification)
                                @php
                                    $type = $notification->data['type'] ?? null;
                                    $icon = match ($type) {
                                        'order_sync_failed' => 'feather-refresh-cw',
                                        'low_stock' => 'feather-package',
                                        'order_return_requested' => 'feather-corner-up-left',
                                        'shipping_token_expired' => 'feather-truck',
                                        default => 'feather-bell',
                                    };
                                    $color = match ($type) {
                                        'order_sync_failed', 'shipping_token_expired' => 'danger',
                                        'low_stock' => 'warning',
                                        'order_return_requested' => 'info',
                                        default => 'primary',
                                    };
                                    $isUnread = $notification->read_at === null;
                                @endphp
                                <tr class="{{ $isUnread ? 'bg-soft-primary' : '' }}">
                                    <td class="ps-3">
                                        <div class="avatar-text avatar-sm rounded bg-soft-{{ $color }} text-{{ $color }}">
                                            <i class="{{ $icon }}"></i>
                                        </div>
                                    </td>
                                    <td class="fs-13 {{ $isUnread ? 'fw-semibold text-dark' : 'text-muted' }}">
                                        {{ $notification->data['message'] ?? '' }}
                                    </td>
                                    <td class="fs-12 text-muted">
                                        {{ $notification->created_at->format('d M, Y H:i') }} &middot; {{ $notification->created_at->diffForHumans() }}
                                    </td>
                                    <td class="text-end pe-3">
                                        @if($isUnread)
                                            <form action="{{ route('notifications.mark-as-read', $notification->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-brand">
                                                    <i class="feather-check me-1"></i> Mark as Read
                                                </button>
                                            </form>
                                        @else
                                            <span class="fs-12 text-muted">
                                                <i class="feather-check-circle text-success me-1"></i> Read
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No notifications found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($notifications->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $notifications->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
