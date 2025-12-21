@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Categories</h1>
                    @can('add categories')
                        <a href="{{ route('categories.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Category</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Categories</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Name</th>
                    <th>Parent Category</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->id }}</td>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->parent_category->name ?? '' }}</td>
                        <td>{{ \Carbon\Carbon::parse($category->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                @can('edit categories')
                                    <a href="{{ route('categories.edit', $category->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('delete categories')
                                    <form action="{{ route('categories.destroy', $category->id) }}" method="POST" id="category-{{ $category->id }}-delete-form">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <a href="javascript:void(0)" data-id="{{ $category->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $categories->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#category-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
