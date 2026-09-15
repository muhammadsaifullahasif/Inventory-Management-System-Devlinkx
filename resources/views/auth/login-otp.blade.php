@extends('layouts.auth')

@section('content')
    <div class="creative-card-body card-body p-sm-5">
        <h2 class="fs-20 fw-bolder mb-4">Verify Login</h2>
        <h4 class="fs-13 fw-bold mb-2">Enter the verification code</h4>
        <p class="fs-12 fw-medium text-muted">A 6-digit code was sent to the configured login authentication email(s). Enter it below to finish signing in.</p>
        <form action="{{ route('login.otp.verify') }}" method="POST" class="w-100 mt-4 pt-2">
            @csrf
            <div class="mb-4">
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" placeholder="Verification code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus>
                @error('code')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mt-5">
                <button type="submit" class="btn btn-lg btn-primary w-100">Verify &amp; Login</button>
            </div>
        </form>
        <div class="mt-4 text-muted">
            <a href="{{ route('login') }}" class="fs-11 text-primary">Back to login</a>
        </div>
    </div>
@endsection
