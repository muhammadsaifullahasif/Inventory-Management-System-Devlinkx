@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Role New</h1>
                    <a href="{{ route('roles.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Role</a>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles</a></li>
                        <li class="breadcrumb-item active">Role New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('roles.store') }}" method="post">
            @csrf
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Role Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="permission">Permissions:</label>
                <div class="row">
                    @foreach ($permissions as $permission)
                        <div class="col-md-3 mb-3">
                            <div class="form-group form-check">
                                <input type="checkbox" id="{{ $permission->id }}" value="{{ $permission->name }}" name="permission[]" class="form-check-input">
                                <label for="{{ $permission->id }}" class="form-check-label">{{ $permission->name }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
