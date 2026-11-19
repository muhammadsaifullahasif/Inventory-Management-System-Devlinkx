@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Settings</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item">Messages</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    @include('settings._nav')

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
        <form method="POST" action="{{ route('settings.messages.auto-reply.update') }}">
            @csrf
            @method('PUT')
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Office hours &amp; automatic reply</h5>
                    <span class="badge {{ $officeIsOpen ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">
                        Team is {{ $officeIsOpen ? 'online' : 'offline' }} now ({{ $officeTimezone }})
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="auto_reply_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="auto-reply-enabled" name="auto_reply_enabled" value="1" @checked(old('auto_reply_enabled', $settings->auto_reply_enabled))>
                        <label class="form-check-label fw-semibold" for="auto-reply-enabled">Send an automatic reply when a buyer writes outside office hours</label>
                    </div>
                    <p class="text-muted fs-12">
                        The buyer gets one automatic message, then none for the cooldown period. Platform notices and conversations you
                        have already answered never get one. The conversation still shows as needing a team reply.
                        Automatic replies are also switched on or off per store below.
                    </p>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <label class="form-label" for="timezone">Time zone</label>
                            <select class="form-select mb-3" id="timezone" name="timezone">
                                @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', $officeTimezone) === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>

                            <label class="form-label">Office hours</label>
                            @foreach($days as $key => $label)
                                @php
                                    $day = $settings->office_hours[$key] ?? null;
                                    $closed = old("hours.$key.closed", $day === null ? 1 : 0);
                                @endphp
                                <div class="d-flex align-items-center gap-2 mb-2 office-day">
                                    <span style="width:90px">{{ $label }}</span>
                                    <input type="time" class="form-control form-control-sm" style="max-width:130px" name="hours[{{ $key }}][open]" value="{{ old("hours.$key.open", $day['open'] ?? '08:00') }}" @disabled($closed)>
                                    <span>to</span>
                                    <input type="time" class="form-control form-control-sm" style="max-width:130px" name="hours[{{ $key }}][close]" value="{{ old("hours.$key.close", $day['close'] ?? '17:00') }}" @disabled($closed)>
                                    <div class="form-check ms-2 mb-0">
                                        <input class="form-check-input" type="checkbox" name="hours[{{ $key }}][closed]" value="1" data-closed-toggle id="closed-{{ $key }}" @checked($closed)>
                                        <label class="form-check-label" for="closed-{{ $key }}">Closed</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label" for="auto-reply-text">Automatic message</label>
                            <textarea class="form-control" id="auto-reply-text" name="auto_reply_text" rows="5" maxlength="2000">{{ old('auto_reply_text', $settings->auto_reply_text) }}</textarea>
                            <div class="form-text mb-3">You can use <code>{buyer_name}</code>; it becomes the buyer's name, or "there" if it is not known.</div>

                            <label class="form-label" for="cooldown">Minimum hours between automatic replies in the same conversation</label>
                            <input type="number" class="form-control mb-3" style="max-width:120px" id="cooldown" name="auto_reply_cooldown_hours" min="1" max="168" value="{{ old('auto_reply_cooldown_hours', $settings->auto_reply_cooldown_hours) }}">

                            <label class="form-label">Stores that send automatic replies</label>
                            @forelse($stores as $s)
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch" name="stores[]" value="{{ $s['channel']->id }}" id="store-{{ $s['channel']->id }}" @checked($s['state']->auto_reply_enabled)>
                                    <label class="form-check-label" for="store-{{ $s['channel']->id }}">{{ $s['channel']->name }}</label>
                                </div>
                            @empty
                                <p class="text-muted mb-0">No stores with messaging support yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="feather-save me-1"></i>Save office hours &amp; automatic reply</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Add a saved reply</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.messages.replies.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="new-title">Title</label>
                            <input type="text" class="form-control" id="new-title" name="title" maxlength="120" value="{{ old('title') }}" required placeholder="e.g. Shipping time">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="new-body">Message</label>
                            <textarea class="form-control" id="new-body" name="body" rows="3" maxlength="2000" required placeholder="Hi {buyer_name}, ...">{{ old('body') }}</textarea>
                            <div class="form-text">
                                Placeholders filled in when a reply is inserted:
                                @foreach($placeholders as $ph)<code>{{ $ph }}</code> @endforeach
                                If the conversation has no matching value, the placeholder stays as written so you can edit it before sending.
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i class="feather-plus me-1"></i>Add reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Saved replies ({{ $replies->count() }})</h5>
            </div>
            <div class="card-body">
                @forelse($replies as $reply)
                    <form method="POST" action="{{ route('settings.messages.replies.update', $reply) }}" class="border rounded p-3 mb-3">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" maxlength="120" value="{{ $reply->title }}" required>
                                <div class="form-check form-switch mt-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="active-{{ $reply->id }}" @checked($reply->is_active)>
                                    <label class="form-check-label" for="active-{{ $reply->id }}">Available in the inbox</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" name="body" rows="3" maxlength="2000" required>{{ $reply->body }}</textarea>
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="submit" class="btn btn-sm btn-light-danger" form="delete-reply-{{ $reply->id }}" onclick="return confirm('Delete this saved reply?')">Delete</button>
                        </div>
                    </form>
                    <form id="delete-reply-{{ $reply->id }}" method="POST" action="{{ route('settings.messages.replies.destroy', $reply) }}" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <p class="text-muted mb-0">No saved replies yet. Add the answers you send most often, then insert them from the reply box in the inbox.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // A closed day has no opening or closing time: grey out (and do not submit) that day's time inputs.
    document.querySelectorAll('.office-day [data-closed-toggle]').forEach(function (box) {
        var apply = function () {
            box.closest('.office-day').querySelectorAll('input[type="time"]').forEach(function (t) {
                t.disabled = box.checked;
            });
        };
        box.addEventListener('change', apply);
        apply();
    });
</script>
@endpush
