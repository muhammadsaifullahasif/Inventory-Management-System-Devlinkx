@extends('layouts.auth')

@section('content')
    <div class="creative-card-body card-body p-sm-5">
        <h2 class="fs-20 fw-bolder mb-4">Reset</h2>
        <h4 class="fs-13 fw-bold mb-2">Reset to your password</h4>
        <p class="fs-12 fw-medium text-muted">You forgot your password? Here you can easily retrieve a new password.</p>
        <form action="{{ route('password.email') }}" method="POST" class="w-100 mt-4 pt-2">
            @csrf
            <div class="mb-4">
                <input type="email" class="form-control @error('email') is-invaild @enderror" name="email" value="{{ $email ?? old('email') }}" placeholder="Email" required>
                @error('email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mt-5">
                <button type="submit" class="btn btn-lg btn-primary w-100">Send Password Reset Link</button>
            </div>
        </form>
        {{-- <div class="mt-5 text-muted">
            <span> Don't have an account?</span>
            <a href="{{ route('register') }}" class="fw-bold">Create an Account</a>
        </div> --}}
    </div>
@endsection
