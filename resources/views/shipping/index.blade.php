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
            <div class="card-body pb-0 d-flex align-items-center justify-content-between">
                @can('delete shipping')
                    @include('partials.bulk-actions-bar', ['itemName' => 'carriers'])
                @endcan
                <div class="ms-auto d-flex align-items-center gap-2">
                    @php
                        $shippingColumns = [
                            ['key' => 'id', 'label' => '#', 'default' => true],
                            ['key' => 'name', 'label' => 'Name', 'default' => true],
                            ['key' => 'type', 'label' => 'Type', 'default' => true],
                            ['key' => 'account', 'label' => 'Account #', 'default' => true],
                            ['key' => 'default_service', 'label' => 'Default Service', 'default' => true],
                            ['key' => 'units', 'label' => 'Units', 'default' => true],
                            ['key' => 'mode', 'label' => 'Mode', 'default' => true],
                            ['key' => 'default', 'label' => 'Default', 'default' => true],
                            ['key' => 'address_validation', 'label' => 'Address Validation', 'default' => true],
                            ['key' => 'status', 'label' => 'Status', 'default' => true],
                        ];
                    @endphp
                    @include('partials.column-toggle', ['tableId' => 'shippingTable', 'cookieName' => 'shipping_columns', 'columns' => $shippingColumns])
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="shippingTable">
                        <thead>
                            <tr>
                                @can('delete shipping')
                                    <th class="ps-3" style="width: 40px;">
                                        <div class="btn-group mb-1">
                                            <div class="custom-control custom-checkbox ms-1">
                                                <input type="checkbox" class="custom-control-input" id="selectAll" title="Select all on this page">
                                                <label for="selectAll" class="custom-control-label"></label>
                                            </div>
                                        </div>
                                    </th>
                                @endcan
                                @php
                                    $currentSort = request('sort_by', 'id');
                                    $currentOrder = request('sort_order', 'desc');
                                    $sortableColumns = [
                                        'id' => ['label' => '#', 'column' => 'id', 'style' => '', 'sort' => true],
                                        'name' => ['label' => 'Name', 'column' => 'name', 'style' => '', 'sort' => true],
                                        'type' => ['label' => 'Type', 'column' => 'type', 'style' => '', 'sort' => false],
                                        'account' => ['label' => 'Account #', 'column' => 'account', 'style' => '', 'sort' => false],
                                        'default_service' => ['label' => 'Default Service', 'column' => 'default_service', 'style' => '', 'sort' => false],
                                        'units' => ['label' => 'Units', 'column' => 'units', 'style' => '', 'sort' => false],
                                        'mode' => ['label' => 'Mode', 'column' => 'mode', 'style' => '', 'sort' => false],
                                        'default' => ['label' => 'Default', 'column' => 'default', 'style' => '', 'sort' => false],
                                        'address_validation' => ['label' => 'Addr. Validation', 'column' => 'address_validation', 'style' => '', 'sort' => false],
                                        'status' => ['label' => 'Status', 'column' => 'status', 'style' => '', 'sort' => false],
                                    ];
                                @endphp
                                @foreach ($sortableColumns as $key => $col)
                                    <th data-column="{{ $key }}" @if($col['style']) style="{{ $col['style'] }}" @endif>
                                        @if($col['sort'])
                                            @php
                                                $isActive = $currentSort === $col['column'];
                                                $nextOrder = ($isActive && $currentOrder === 'asc') ? 'desc' : 'asc';
                                                $sortUrl = request()->fullUrlWithQuery(['sort_by' => $col['column'], 'sort_order' => $nextOrder]);
                                            @endphp
                                            <a href="{{ $sortUrl }}" class="d-flex align-items-center text-dark text-decoration-none sortable-header {{ $isActive ? 'active' : '' }}">
                                                {{ $col['label'] }}
                                                <span class="sort-arrows ms-1">
                                                    @if($isActive)
                                                        @if($currentOrder === 'asc')
                                                            <i class="feather-arrow-up fs-12"></i>
                                                        @else
                                                            <i class="feather-arrow-down fs-12"></i>
                                                        @endif
                                                    @else
                                                        <i class="feather-chevrons-up fs-10 text-muted opacity-50"></i>
                                                    @endif
                                                </span>
                                            </a>
                                        @else
                                            {{ $col['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shippings as $shipping)
                                <tr>
                                    @can('delete shipping')
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
                                    @endcan
                                    <td data-column="id">{{ $loop->iteration }}</td>
                                    <td data-column="name"><span class="fw-semibold">{{ $shipping->name }}</span></td>
                                    <td data-column="type"><span class="badge bg-soft-secondary text-secondary">{{ strtoupper($shipping->type) }}</span></td>
                                    <td data-column="account_number">{{ $shipping->account_number ?: '-' }}</td>
                                    <td data-column="default_service"><span class="fs-12">{{ $shipping->default_service ?: '-' }}</span></td>
                                    <td data-column="units" class="text-nowrap"><span class="fs-12 text-muted">{{ $shipping->weight_unit }} / {{ $shipping->dimension_unit }}</span></td>
                                    <td data-column="mode">
                                        @if ($shipping->is_sandbox)
                                            <span class="badge bg-soft-warning text-warning">Sandbox</span>
                                        @else
                                            <span class="badge bg-soft-success text-success">Live</span>
                                        @endif
                                    </td>
                                    <td data-column="default">
                                        @if ($shipping->is_default)
                                            <span class="badge bg-soft-primary text-primary">Default</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td data-column="address_validation">
                                        @if ($shipping->is_address_validation)
                                            <span class="badge bg-soft-info text-info">Enabled</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td data-column="status">
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

@can('delete shipping')
    @include('partials.bulk-delete-scripts', ['routeName' => 'shipping.bulk-delete', 'itemName' => 'carriers'])
@endcan
@endpush
