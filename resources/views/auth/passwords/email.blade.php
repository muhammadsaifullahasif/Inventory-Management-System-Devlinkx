@extends('layouts.auth')

@section('content')
<div class="card">
    <div class="card-body login-card-body">
        <p class="login-box-msg">You forgot your password? Here you can easily retrieve a new password.</p>
        
        <form action="{{ route('password.email') }}" method="post">
            @csrf
            <div class="input-group mb-3">
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required placeholder="Email">
                <div class="input-group-append">
                    <div class="input-group-text">
                        <span class="fas fa-envelope"></span>
                    </div>
                </div>
            </div>
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-block">Send Password Reset Link</button>
                </div>
                <!-- /.col -->
            </div>
        </form>
        
        <p class="mt-3 mb-1">
            <a href="{{ route('login') }}">Login</a>
        </p>
        <p class="mb-0">
            <a href="{{ route('register') }}" class="text-center">Register a new membership</a>
        </p>
    </div>
    <!-- /.login-card-body -->
</div>
@endsection
