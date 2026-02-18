@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Shipping Carriers</h1>
                    @can('add shipping')
                        <a href="{{ route('shipping.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Carrier</a>
                    @endcan
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Shipping</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Account #</th>
                        <th>Default Service</th>
                        <th>Units</th>
                        <th>Mode</th>
                        <th>Default</th>
                        <th>Addr. Validation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shippings as $shipping)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $shipping->name }}</strong></td>
                            <td><span class="badge badge-secondary">{{ strtoupper($shipping->type) }}</span></td>
                            <td>{{ $shipping->account_number ?: '-' }}</td>
                            <td>{{ $shipping->default_service ?: '-' }}</td>
                            <td class="text-nowrap"><small>{{ $shipping->weight_unit }} / {{ $shipping->dimension_unit }}</small></td>
                            <td>
                                @if ($shipping->is_sandbox)
                                    <span class="badge badge-warning">Sandbox</span>
                                @else
                                    <span class="badge badge-success">Live</span>
                                @endif
                            </td>
                            <td>
                                @if ($shipping->is_default)
                                    <span class="badge badge-primary">Default</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($shipping->is_address_validation)
                                    <span class="badge badge-info">Enabled</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge carrier-status-badge badge-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }}"
                                      data-id="{{ $shipping->id }}" style="cursor:pointer;" title="Click to toggle">
                                    {{ $shipping->active_status === '1' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-nowrap">
                                @can('view shipping')
                                    <a href="{{ route('shipping.show', $shipping->id) }}" class="btn btn-info btn-xs">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endcan
                                @can('edit shipping')
                                    <a href="{{ route('shipping.edit', $shipping->id) }}" class="btn btn-warning btn-xs">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endcan
                                @can('delete shipping')
                                    <form action="{{ route('shipping.destroy', $shipping->id) }}" method="POST" class="d-inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-3">
                                No shipping carriers found. <a href="{{ route('shipping.create') }}">Add one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $(document).on('submit', '.delete-form', function (e) {
        e.preventDefault();
        if (confirm('Delete this shipping carrier?')) { $(this).off('submit').submit(); }
    });
    $(document).on('click', '.carrier-status-badge', function () {
        var badge = $(this), id = badge.data('id');
        $.ajax({
            url: '/shipping/' + id + '/toggle-status',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) {
                    if (res.active) { badge.removeClass('badge-secondary').addClass('badge-success').text('Active'); }
                    else { badge.removeClass('badge-success').addClass('badge-secondary').text('Inactive'); }
                }
            }
        });
    });
});
</script>
@endpush