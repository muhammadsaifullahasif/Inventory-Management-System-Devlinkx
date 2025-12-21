@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Categories Edit</h1>
                    @can('add categories')
                        <a href="{{ route('categories.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Category</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categories</a></li>
                        <li class="breadcrumb-item active">Category Edit</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body w-50">
        <form action="{{ route('categories.update', $category->id) }}" method="post">
            @csrf
            @method("PUT")
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Category Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="parent_id">Parent Category:</label>
                <select name="parent_id" id="parent_id" class="form-control">
                    <option value="">Select Parent Category</option>
                    @foreach ($parent_categories as $parent_category)
                        <option value="{{ $parent_category->id }}" {{ (old('parent_id', $category->parent_id) == $parent_category->id) ? 'selected' : '' }}>{{ $parent_category->name }}</option>
                    @endforeach
                </select>
                @error('parent_id')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection
