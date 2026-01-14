@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Sales Channels</h1>
                    @can('add purchases')
                        <a href="{{ route('sales-channels.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Sales Channel</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Sales Channels</li>
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
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales_channels as $sales_channel)
                    <tr>
                        <td>{{ $sales_channel->id }}</td>
                        <td>{{ $sales_channel->name }}</td>
                        <td>{{ \Carbon\Carbon::parse($sales_channel->created_at)->format('d M, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('ebay.inventory.items', $sales_channel->id) }}" class="btn btn-success btn-sm">Import Products</a>
                                @can('edit sales_channels')
                                    <a href="{{ route('sales-channels.edit', $sales_channel->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('delete sales_channels')
                                    <form action="{{ route('sales-channels.destroy', $sales_channel->id) }}" method="POST" id="sales-channel-{{ $sales_channel->id }}-delete-form">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    <a href="javascript:void(0)" data-id="{{ $sales_channel->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">No Record Found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $sales_channels->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#sales-channel-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
