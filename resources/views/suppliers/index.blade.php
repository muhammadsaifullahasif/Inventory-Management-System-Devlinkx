@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Suppliers</h1>
                    @can('add suppliers')
                        <a href="{{ route('suppliers.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Supplier</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Suppliers</li>
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
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>{{ $supplier->id }}</td>
                        <td>{{ (($supplier->last_name != '') ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name) }}</td>
                        <td><a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a></td>
                        <td>{{ $supplier->phone }}</td>
                        <td>{!! ($supplier->active_status == 1) ? "<span class='badge badge-success'>Active</span>" : "<span class='badge badge-danger'>Inactive</span>" !!}</td>
                        <td>{{ \Carbon\Carbon::parse($supplier->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                @can('edit suppliers')
                                    <a href="{{ route('suppliers.edit', $supplier->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('delete suppliers')
                                    <form action="{{ route('suppliers.destroy', $supplier->id) }}" method="POST" id="supplier-{{ $supplier->id }}-delete-form">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <a href="javascript:void(0)" data-id="{{ $supplier->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $suppliers->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#supplier-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
