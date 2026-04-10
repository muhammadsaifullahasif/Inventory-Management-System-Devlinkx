@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Receive Stock</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item">Receive Stock</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Purchases</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@push('styles')
<style>
    .receive-input {
        width: 100px;
    }
    .status-badge {
        font-size: 0.75rem;
    }
    .fully-received {
        background-color: #d4edda !important;
    }
    .partially-received {
        background-color: #fff3cd !important;
    }
    .bg-soft-success {
        background-color: #d4edda !important;
    }
    .receive-qty-input:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
    .bg-soft-success:focus {
        background-color: #c3e6cb !important;
    }
</style>
@endpush

@section('content')
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="feather-alert-triangle me-2"></i>{{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <!-- Purchase Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-file-text me-2"></i>Purchase Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 120px;">Purchase #:</td>
                            <td><strong>{{ $purchase->purchase_number }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Supplier:</td>
                            <td>{{ $purchase->supplier->first_name }} {{ $purchase->supplier->last_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Warehouse:</td>
                            <td><span class="badge bg-soft-info text-info">{{ $purchase->warehouse->name }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'partial' => 'info',
                                        'received' => 'success',
                                        'cancelled' => 'danger',
                                    ];
                                    $statusColor = $statusColors[$purchase->purchase_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ ucfirst($purchase->purchase_status) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td>{{ $purchase->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                    @if($purchase->purchase_note)
                        <hr class="my-3">
                        <p class="text-muted mb-1">Note:</p>
                        <p class="mb-0">{{ $purchase->purchase_note }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Summary Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="feather-pie-chart me-2"></i>Receiving Summary</h5>
                </div>
                <div class="card-body">
                    @php
                        $totalOrdered = $purchase->purchase_items->sum('quantity');
                        $totalReceived = $purchase->purchase_items->sum('received_quantity');
                        $totalPending = $totalOrdered - $totalReceived;
                        $percentReceived = $totalOrdered > 0 ? round(floor(($totalReceived / $totalOrdered) * 100)) : 0;
                    @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Progress</span>
                            <span class="fw-semibold">{{ $percentReceived }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percentReceived }}%"></div>
                        </div>
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Total Ordered:</td>
                            <td class="text-end fw-semibold">{{ number_format($totalOrdered, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Received:</td>
                            <td class="text-end fw-semibold text-success">{{ number_format($totalReceived, 0) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pending:</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format($totalPending, 0) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Receive Items Card -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="feather-package me-2"></i>Receive / Edit Items</h5>
                    <div class="card-tools d-flex align-items-center gap-2">
                        <select id="inlineBrandSearch" class="form-select form-control-sm" style="width:200px;">
                            <option value="">Filter Brand</option>
                        </select>
                        <select id="inlineCategorySearch" class="form-select form-control-sm" style="width:200px;">
                            <option value="">Filter Category</option>
                        </select>
                        <input type="text" id="inlineSearch" class="form-control form-control-sm"
                            placeholder="Quick filter..." style="width:200px;">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="receiveAllBtn">
                            <i class="feather-check-square me-1"></i>Receive All Items
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form action="{{ route('purchases.receive.store', $purchase->id) }}" method="POST" id="receiveForm">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="productsTable">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 60px;">Image</th>
                                        <th style="max-width: 250px;">Product</th>
                                        <th style="width: 90px;" class="text-center">Ordered</th>
                                        <th style="width: 90px;" class="text-center">Received</th>
                                        <th style="width: 90px;" class="text-center">Pending</th>
                                        <th style="width: 120px;" class="text-center">
                                            New Qty
                                            <i class="feather-info fs-12 text-muted" data-bs-toggle="tooltip"
                                               title="Set the new total received quantity. You can increase or decrease as needed."></i>
                                        </th>
                                        <th style="width: 140px;">Rack</th>
                                        <th style="width: 80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="productsTableBody">
                                    @foreach ($purchase->purchase_items as $index => $item)
                                        @php
                                            $ordered = (float) $item->quantity;
                                            $received = (float) $item->received_quantity;
                                            $pending = $ordered - $received;
                                            $isFullyReceived = $received >= $ordered;
                                            $isPartiallyReceived = $received > 0 && $received < $ordered;
                                        @endphp
                                        <tr class="product-row {{ $isFullyReceived ? 'fully-received' : ($isPartiallyReceived ? 'partially-received' : '') }}"
                                            data-id="{{ $item->product->id }}"
                                            data-name="{{ strtolower($item->product->name) }}"
                                            data-sku="{{ strtolower($item->product->sku) }}"
                                            data-barcode="{{ strtolower($item->product->barcode) }}"
                                            data-brand="{{ $item->product->brand->name }}"
                                            data-category="{{ $item->product->category->name }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                @if($item->product && $item->product->getImageUrl())
                                                    {{-- <img src="{{ $item->product->getImageUrl() }}" alt="{{ $item->name }}" class="rounded" style="width: 45px; height: 45px; object-fit: cover;"> --}}
                                                @else
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                        <i class="feather-image text-muted fs-12"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div style="white-space: normal; width: 250px; display: block;" class="fw-semibold">{{ $item->name }}</div>
                                                <small class="text-muted">SKU: {{ $item->sku }}</small>
                                                <input type="hidden" name="items[{{ $index }}][item_id]" value="{{ $item->id }}">
                                            </td>
                                            <td class="text-center">
                                                <span class="fw-semibold">{{ number_format($ordered, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-success text-success">{{ number_format($received, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-warning text-warning pending-qty" data-pending="{{ $pending }}">{{ number_format($pending, 0) }}</span>
                                            </td>
                                            <td class="text-center">
                                                {{-- All items are now editable, including fully received ones --}}
                                                <input type="number" name="items[{{ $index }}][receive_quantity]"
                                                       value="{{ (int) $received }}" min="0" max="{{ (int) $ordered }}" step="1"
                                                       class="form-control form-control-sm receive-input text-center receive-qty-input {{ $isFullyReceived ? 'bg-soft-success' : '' }}"
                                                       data-max="{{ (int) $ordered }}"
                                                       data-current="{{ (int) $received }}"
                                                       data-ordered="{{ (int) $ordered }}">
                                            </td>
                                            <td>
                                                {{-- All rack selectors are now enabled --}}
                                                <select name="items[{{ $index }}][rack_id]" class="form-select form-select-sm {{ $isFullyReceived ? 'bg-soft-success' : '' }}">
                                                    @foreach ($racks as $rack)
                                                        <option value="{{ $rack->id }}" {{ $item->rack_id == $rack->id ? 'selected' : '' }}>
                                                            {{ $rack->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                @if($isFullyReceived)
                                                    <span class="badge bg-success status-badge">Received</span>
                                                @elseif($isPartiallyReceived)
                                                    <span class="badge bg-warning status-badge">Partial</span>
                                                @else
                                                    <span class="badge bg-secondary status-badge">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">Additional quantity to receive: </span>
                                <strong id="totalToReceive" class="text-success">0</strong>
                                <small class="text-muted ms-3">(You can also decrease previously received quantities)</small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="feather-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const allRows = Array.from(document.querySelectorAll('.product-row'));

            const inlineSearch = document.getElementById('inlineSearch');
            const inlineBrandSearch = document.getElementById('inlineBrandSearch');
            const inlineCategorySearch = document.getElementById('inlineCategorySearch');

            function applyFilters() {
                const searchQuery = (inlineSearch?.value || '').trim().toLowerCase();
                const brandQuery = (inlineBrandSearch?.value || '').trim().toLowerCase();
                const categoryQuery = (inlineCategorySearch?.value || '').trim().toLowerCase();

                let visible = 0;

                allRows.forEach(row => {
                    const name = (row.dataset.name || '').toLowerCase();
                    const sku = (row.dataset.sku || '').toLowerCase();
                    const barcode = (row.dataset.barcode || '').toLowerCase();
                    const brand = (row.dataset.brand || '').toLowerCase();
                    const category = (row.dataset.category || '').toLowerCase();

                    const matchSearch = !searchQuery ||
                        name.includes(searchQuery) ||
                        sku.includes(searchQuery) ||
                        barcode.includes(searchQuery);

                    const matchBrand = !brandQuery || brand === brandQuery;
                    const matchCategory = !categoryQuery || category === categoryQuery;

                    const match = matchSearch && matchBrand && matchCategory;

                    row.style.display = match ? '' : 'none';

                    if (match) visible++;
                });
            }

            // Events
            if (inlineSearch) {
                inlineSearch.addEventListener('input', applyFilters);
            }

            if (inlineBrandSearch) {
                inlineBrandSearch.addEventListener('change', applyFilters);
            }

            if (inlineCategorySearch) {
                inlineCategorySearch.addEventListener('change', applyFilters);
            }

        });

        $(document).ready(function(){

            // Populate Brands and Categories
            function populateBrandsAndCategories() {
                let brands = new Set();
                let categories = new Set();
                $('#productsTableBody tr').each(function () {
                    let brand = $(this).data('brand');
                    let category = $(this).data('category');

                    if (brand) brands.add(brand);
                    if (category) categories.add(category);
                });

                // Populate Brand Filter
                brands.forEach(function (brand) {
                    $('#inlineBrandSearch').append(`<option value="${brand}">${brand}</option>`);
                });

                // Populate Category Filter
                categories.forEach(function (category) {
                    $('#inlineCategorySearch').append(`<option value="${category}">${category}</option>`);
                });
            }
            populateBrandsAndCategories();

            // Calculate total to receive
            function calculateTotal() {
                var totalNewlyReceiving = 0;
                var hasChanges = false;

                $('.receive-qty-input').each(function(){
                    var val = parseFloat($(this).val()) || 0;
                    var current = parseFloat($(this).attr('data-current')) || 0;
                    var pending = parseFloat($(this).attr('data-ordered')) || 0;

                    // Calculate how much is newly being received (compared to current)
                    var difference = val - current;
                    if (difference > 0) {
                        totalNewlyReceiving += difference;
                    }

                    // Check if there are any changes from current state
                    if (val !== current) {
                        hasChanges = true;
                    }
                });

                $('#totalToReceive').text(totalNewlyReceiving);

                // Enable submit button always - allow editing of existing received quantities
                // The backend will validate the changes
                $('#submitBtn').prop('disabled', false).addClass('btn-primary').removeClass('btn-secondary');
            }

            // Update total on input change
            $(document).on('input change', '.receive-qty-input', function(){
                var max = parseFloat($(this).attr('data-max')) || 0;
                var val = parseFloat($(this).val()) || 0;

                // Ensure value doesn't exceed max (ordered quantity)
                if (val > max) {
                    $(this).val(Math.floor(max));
                }
                if (val < 0) {
                    $(this).val(0);
                }

                calculateTotal();
            });

            // Receive all pending button - sets all items to their ordered quantity
            $('#receiveAllBtn').click(function(e){
                e.preventDefault();
                $('.receive-qty-input').each(function(){
                    var ordered = parseFloat($(this).attr('data-ordered')) || 0;
                    $(this).val(Math.floor(ordered));
                });
                calculateTotal();
            });

            // Initial calculation
            calculateTotal();

            // Form submission - only send changed items
            $('#receiveForm').submit(function(e){
                e.preventDefault();

                var hasChanges = false;
                var changedItems = [];

                // Find all items that have changed
                $('.receive-qty-input').each(function(){
                    var $input = $(this);
                    var newValue = parseFloat($input.val()) || 0;
                    var currentValue = parseFloat($input.attr('data-current')) || 0;

                    // Check if value has changed
                    if (newValue !== currentValue) {
                        hasChanges = true;

                        // Get the row index from the input name
                        var name = $input.attr('name');
                        var match = name.match(/items\[(\d+)\]/);
                        if (match) {
                            var index = match[1];
                            changedItems.push(index);
                        }
                    }
                });

                if (!hasChanges) {
                    alert('No changes detected. Please modify at least one item.');
                    return false;
                }

                // Remove unchanged items from form submission
                $('input[name^="items["], select[name^="items["]').each(function(){
                    var $input = $(this);
                    var name = $input.attr('name');
                    var match = name.match(/items\[(\d+)\]/);

                    if (match) {
                        var index = match[1];

                        // If this item hasn't changed, remove it from submission
                        if (!changedItems.includes(index)) {
                            $input.prop('disabled', true);
                        }
                        // console.log(name);
                    }
                });

                // Submit the form
                this.submit();
            });
        });
    </script>
@endpush
