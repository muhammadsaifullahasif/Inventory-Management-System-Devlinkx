@extends('layouts.auth')

@section('content')
    <div class="creative-card-body card-body p-sm-5">
        <h2 class="fs-20 fw-bolder mb-4">Login</h2>
        <h4 class="fs-13 fw-bold mb-2">Login to your account</h4>
        <p class="fs-12 fw-medium text-muted">Thank you for get back <strong>Nelel</strong> web applications, let's access our the best recommendation for you.</p>
        <form action="{{ route('login') }}" method="POST" class="w-100 mt-4 pt-2">
            @csrf
            <div class="mb-4">
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="Email or Username" value="{{ old('email') }}" required>
                @error('email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Password" value="" required>
                @error('password')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="custom-control-label c-pointer" for="remember">Remember Me</label>
                    </div>
                </div>
                <div>
                    <a href="{{ route('password.request') }}" class="fs-11 text-primary">Forget password?</a>
                </div>
            </div>
            <div class="mt-5">
                <button type="submit" class="btn btn-lg btn-primary w-100">Login</button>
            </div>
        </form>
        <div class="mt-5 text-muted">
            <span> Don't have an account?</span>
            <a href="{{ route('register') }}" class="fw-bold">Create an Account</a>
        </div>
    </div>
@endsection
