@extends('layouts.auth')

@section('content')
<div class="card">
    <div class="card-body login-card-body">
        <p class="login-box-msg">You forgot your password? Here you can easily retrieve a new password.</p>
        
        <form action="{{ route('password.confirm') }}" method="post">
            @csrf
            <div class="input-group mb-3">
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required placeholder="Password">
                <div class="input-group-append">
                    <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                    </div>
                </div>
            </div>
            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-block">Confirm Password</button>
                </div>
                <!-- /.col -->
            </div>
        </form>
        
        <p class="mt-3 mb-1">
            <a href="{{ route('password.request') }}">I forgot my password</a>
        </p>
    </div>
    <!-- /.login-card-body -->
</div>
@endsection
