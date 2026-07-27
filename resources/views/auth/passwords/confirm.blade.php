@extends('layouts.auth')

@section('content')
    <div class="creative-card-body card-body p-sm-5">
        <h2 class="fs-20 fw-bolder mb-4">Confirm</h2>
        <h4 class="fs-13 fw-bold mb-2">Confirm your password</h4>
        {{-- <p class="fs-12 fw-medium text-muted">Confirm your password. Here you can easily retrieve a new password.</p> --}}
        <form action="{{ route('password.confirm') }}" method="POST" class="w-100 mt-4 pt-2">
            @csrf
            <div class="mb-4">
                <input type="password" class="form-control @error('password') is-invaild @enderror" name="password" placeholder="Password" required>
                @error('password')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mt-5">
                <button type="submit" class="btn btn-lg btn-primary w-100">Confirm Password</button>
            </div>
        </form>
        <div class="mt-5 text-muted">
            <a href="{{ route('password.request') }}" class="fw-bold">I forgot my password</a>
        </div>
    </div>
@endsection
