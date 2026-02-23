@extends('layouts.auth')

@section('content')
    <div class="creative-card-body card-body p-sm-5">
        <h2 class="fs-20 fw-bolder mb-4">Verify your email address</h2>
        {{-- <h4 class="fs-13 fw-bold mb-2">Reset to your password</h4> --}}
        {{-- <p class="fs-12 fw-medium text-muted">You forgot your password? Here you can easily retrieve a new password.</p> --}}
        @if (session('resent'))
            <div class="alert alert-success" role="alert">
                {{ __('A fresh verification link has been sent to your email address.') }}
            </div>
        @endif

        {{ __('Before proceeding, please check your email for a verification link.') }}
        {{ __('If you did not receive the email') }},
        <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
        </form>
    </div>
@endsection
