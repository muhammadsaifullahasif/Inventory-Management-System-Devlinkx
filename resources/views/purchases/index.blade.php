@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Purchases</h1>
                    @can('add purchases')
                        <a href="{{ route('purchases.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Purchase</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Purchases</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <!-- Filters Card -->
    <div class="card card-outline card-primary mb-3">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body py-2">
            <form action="{{ route('purchases.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Purchase Number..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Supplier</label>
                        <select name="supplier_id" class="form-control form-control-sm">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->first_name }} {{ $supplier->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Warehouse</label>
                        <select name="warehouse_id" class="form-control form-control-sm">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ request('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Status</label>
                        <select name="purchase_status" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('purchase_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="received" {{ request('purchase_status') == 'received' ? 'selected' : '' }}>Received</option>
                            <option value="cancelled" {{ request('purchase_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Date From</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small mb-1">Date To</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="{{ route('purchases.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times mr-1"></i>Clear</a>
                    </div>
                    <div class="col-md-6 mb-2 text-right">
                        <span class="text-muted small">Showing {{ $purchases->firstItem() ?? 0 }} - {{ $purchases->lastItem() ?? 0 }} of {{ $purchases->total() }} results</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover table-sm">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Purchase Number</th>
                    <th>Supplier</th>
                    <th>Warehouse</th>
                    <th>Total</th>
                    <th>Total Products</th>
                    <th style="width: 150px;">Created at</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>{{ $purchase->id }}</td>
                        <td>{{ $purchase->purchase_number }}</td>
                        <td>{{ (($purchase->supplier->last_name != '') ? $purchase->supplier->first_name . ' ' . $purchase->supplier->last_name : $purchase->supplier->first_name) }}</td>
                        <td>{{ $purchase->warehouse->name }}</td>
                        <td>{{ number_format($purchase->purchase_items->sum(function($item) { return $item->quantity * $item->price; }), 2) }}</td>
                        <td>{{ $purchase->purchase_items->sum('quantity') }}</td>
                        <td>{{ \Carbon\Carbon::parse($purchase->created_at)->format('M d, Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-success btn-sm"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('purchases.edit', $purchase->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('purchases.destroy', $purchase->id) }}" method="POST" id="purchase-{{ $purchase->id }}-delete-form">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <a href="javascript:void(0)" data-id="{{ $purchase->id }}" class="btn btn-danger btn-sm delete-btn"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">No record found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $purchases->links('pagination::bootstrap-5') }}
    </div>
@endsection

@push('scripts')
    <script>
		$(document).ready(function(){
			$(document).on('click', '.delete-btn', function(e){
                var id = $(this).data('id');
				if (confirm('Are you sure to delete the record?')) {
                    $('#purchase-' + id + '-delete-form').submit();
				} else {
                    e.preventDefault();
					return false;
				}
			});
		});
	</script>
@endpush
