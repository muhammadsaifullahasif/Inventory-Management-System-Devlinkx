@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Shipping Carriers</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Shipping</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    @can('add shipping')
                    <a href="{{ route('shipping.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Carrier</span>
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-body pb-0">
                @include('partials.bulk-actions-bar', ['itemName' => 'carriers'])
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width: 40px;">
                                    <div class="btn-group mb-1">
                                        <div class="custom-control custom-checkbox ms-1">
                                            <input type="checkbox" class="custom-control-input" id="selectAll" title="Select all on this page">
                                            <label for="selectAll" class="custom-control-label"></label>
                                        </div>
                                    </div>
                                </th>
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
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shippings as $shipping)
                                <tr>
                                    <td class="ps-3">
                                        <div class="item-checkbox ms-1">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input checkbox row-checkbox" id="{{ $shipping->id }}" data-shipping-id="{{ $shipping->id }}">
                                                <label for="{{ $shipping->id }}" class="custom-control-label"></label>
                                                <input type="hidden" class="shipping-id-input" value="{{ $shipping->id }}" disabled>
                                            </div>
                                        </div>
                                        {{-- <input type="checkbox" class="form-check-input row-checkbox" value="{{ $shipping->id }}"> --}}
                                    </td>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="fw-semibold">{{ $shipping->name }}</span></td>
                                    <td><span class="badge bg-soft-secondary text-secondary">{{ strtoupper($shipping->type) }}</span></td>
                                    <td>{{ $shipping->account_number ?: '-' }}</td>
                                    <td><span class="fs-12">{{ $shipping->default_service ?: '-' }}</span></td>
                                    <td class="text-nowrap"><span class="fs-12 text-muted">{{ $shipping->weight_unit }} / {{ $shipping->dimension_unit }}</span></td>
                                    <td>
                                        @if ($shipping->is_sandbox)
                                            <span class="badge bg-soft-warning text-warning">Sandbox</span>
                                        @else
                                            <span class="badge bg-soft-success text-success">Live</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shipping->is_default)
                                            <span class="badge bg-soft-primary text-primary">Default</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($shipping->is_address_validation)
                                            <span class="badge bg-soft-info text-info">Enabled</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge carrier-status-badge bg-soft-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }} text-{{ $shipping->active_status === '1' ? 'success' : 'secondary' }}"
                                              data-id="{{ $shipping->id }}" style="cursor:pointer;" title="Click to toggle">
                                            {{ $shipping->active_status === '1' ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="hstack gap-2 justify-content-end">
                                            @can('view shipping')
                                                <a href="{{ route('shipping.show', $shipping->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
                                                    <i class="feather-eye"></i>
                                                </a>
                                            @endcan
                                            @can('edit shipping')
                                                <a href="{{ route('shipping.edit', $shipping->id) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="feather-edit-3"></i>
                                                </a>
                                            @endcan
                                            @can('delete shipping')
                                                <form action="{{ route('shipping.destroy', $shipping->id) }}" method="POST" class="d-inline delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="avatar-text avatar-md text-danger border-0 bg-transparent" data-bs-toggle="tooltip" title="Delete">
                                                        <i class="feather-trash-2"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        No shipping carriers found. <a href="{{ route('shipping.create') }}">Add one</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <div>
                    @include('partials.per-page-dropdown', ['perPage' => $perPage])
                </div>
                <div>
                    {{ $shippings->links('pagination::bootstrap-5') }}
                </div>
            </div>
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
                    if (res.active) {
                        badge.removeClass('bg-soft-secondary text-secondary').addClass('bg-soft-success text-success').text('Active');
                    } else {
                        badge.removeClass('bg-soft-success text-success').addClass('bg-soft-secondary text-secondary').text('Inactive');
                    }
                }
            }
        });
    });
});
</script>

@include('partials.bulk-delete-scripts', ['routeName' => 'shipping.bulk-delete', 'itemName' => 'carriers'])
@endpush
