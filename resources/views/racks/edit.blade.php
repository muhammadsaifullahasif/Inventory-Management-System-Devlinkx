@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Rack Edit</h1>
                    @can('add racks')
                        <a href="{{ route('racks.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Rack</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                        <li class="breadcrumb-item active">Rack Edit</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body w-50">
        <form action="{{ route('racks.update', $rack->id) }}" method="post">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $rack->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Rack Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="warehouse_id">Warehouse: <span class="text-danger">*</span></label>
                <select id="warehouse_id" name="warehouse_id" class="form-control @error('warehouse_id') is-invalid @enderror">
                    <option value="">Select Warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ (old('warehouse_id', $rack->warehouse_id) == $warehouse->id || $warehouse->is_default) ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" id="is_default" value="1" name="is_default" class="custom-control-input" {{ (old('is_default', $rack->is_default) == 1) ? 'checked' : '' }}>
                    <label for="is_default" class="custom-control-label">Is Default</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
