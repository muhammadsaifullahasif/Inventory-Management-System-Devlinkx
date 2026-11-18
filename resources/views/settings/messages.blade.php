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
