@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Racks</h1>
                    @can('add racks')
                        <a href="{{ route('racks.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Rack</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Racks</li>
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
                    <th>Warehouse</th>
                    <th style="width: 150px;">Is Default</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($racks as $rack)
                    <tr>
                        <td>{{ $rack->id }}</td>
                        <td>{{ $rack->name }}</td>
                        <td>{{ $rack->warehouse->name }}</td>
                        <td>{!! $rack->is_default ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>' !!}</td>
                        <td>{{ \Carbon\Carbon::parse($rack->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('racks.edit', $rack->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('racks.destroy', $rack->id) }}" method="POST" id="rack-{{ $rack->id }}-delete-form">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <a href="javascript:void(0)" data-id="{{ $rack->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $racks->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#rack-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
