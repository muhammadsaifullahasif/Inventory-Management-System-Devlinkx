@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">User New</h1>
                    <a href="{{ route('users.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add User</a>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                        <li class="breadcrumb-item active">User New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('users.store') }}" method="post">
            @csrf
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="User Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="email">Email: <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="User Email">
                @error('email')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password">Password: <span class="text-danger">*</span></label>
                <input type="password" id="password" name="password" value="{{ old('password') }}" class="form-control @error('password') is-invalid @enderror" placeholder="User Password" autocomplete="off">
                @error('password')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="password-confirm">Retry Password: <span class="text-danger">*</span></label>
                <input type="password" id="password-confirm" class="form-control" name="password_confirmation" placeholder="Retype password">
            </div>
            <div class="mb-3">
                <label for="role">Roles:</label>
                <div class="row">
                    @foreach ($roles as $role)
                        <div class="col-md-3 mb-3">
                            <div class="form-group form-check">
                                <input type="checkbox" id="{{ $role->id }}" value="{{ $role->name }}" name="role[]" class="form-check-input">
                                <label for="{{ $role->id }}" class="form-check-label">{{ $role->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
