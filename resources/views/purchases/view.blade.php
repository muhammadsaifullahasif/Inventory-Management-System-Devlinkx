@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Purchase Details</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                <li class="breadcrumb-item">Purchase Details</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('purchases.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Purchases</span>
                    </a>
                    @can('add purchases')
                    <a href="{{ route('purchases.create') }}" class="btn btn-primary">
                        <i class="feather-plus me-2"></i>
                        <span>Add Purchase</span>
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
            <div class="card-header">
                <h5 class="card-title">Purchase Information</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('purchases.receive.stock', $purchase->id) }}" method="post">
                    @csrf
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label text-muted">Purchase Number</label>
                            <p class="fw-semibold mb-0">{{ $purchase->purchase_number }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Supplier</label>
                            <p class="fw-semibold mb-0">{{ (($purchase->supplier->last_name != '') ? $purchase->supplier->first_name . ' ' . $purchase->supplier->last_name : $purchase->supplier->first_name) }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Warehouse</label>
                            <p class="fw-semibold mb-0">{{ $purchase->warehouse->name }}</p>
                        </div>
                    </div>
                    @if($purchase->purchase_note)
                    <div class="mb-4">
                        <label class="form-label text-muted">Purchase Note</label>
                        <p class="mb-0">{{ $purchase->purchase_note }}</p>
                    </div>
                    @endif

                    <hr class="my-4">
                    <h6 class="mb-3"><i class="feather-package me-2"></i>Purchase Items</h6>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Qty Purchased</th>
                                    <th>Prev. Stock</th>
                                    <th>Unit Price</th>
                                    <th>Avg Cost</th>
                                    <th>Note</th>
                                    <th>Sub Total</th>
                                    <th>Rack</th>
                                </tr>
                            </thead>
                            <tbody id="purchaseProductsTable">
                                @foreach ($purchase->purchase_items as $item)
                                    <tr>
                                        <td>{{ ($loop->index + 1) }}</td>
                                        <td>
                                            <span class="fs-12">{{ $item->sku }}</span>
                                            <input type="hidden" name="product_id[]" value="{{ $item->product_id }}">
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $item->name }}</span>
                                            <span class="d-block fs-11 text-muted">{{ $item->barcode }}</span>
                                        </td>
                                        <td>
                                            <input type="text" name="quantity[]" value="{{ $item->quantity }}" class="form-control form-control-sm quantity" readonly style="width: 80px;">
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-secondary text-secondary">{{ number_format((float) $item->previous_quantity, 2) }}</span>
                                        </td>
                                        <td>
                                            <input type="text" name="price[]" value="{{ $item->price }}" class="form-control form-control-sm price" readonly style="width: 100px;">
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-info text-info">{{ number_format((float) $item->avg_cost, 4) }}</span>
                                        </td>
                                        <td>
                                            <input type="text" name="note[]" value="{{ $item->note }}" class="form-control form-control-sm note" readonly style="width: 120px;">
                                        </td>
                                        <td>
                                            <input type="text" name="subtotal[]" value="{{ ($item->price * $item->quantity) }}" class="form-control form-control-sm subtotal" readonly style="width: 100px;">
                                        </td>
                                        <td>
                                            <select name="rack_id[]" class="form-select form-select-sm" style="width: 120px;">
                                                <option value="">Select Rack</option>
                                                @foreach ($purchase->warehouse->racks as $rack)
                                                    <option value="{{ $rack->id }}">{{ $rack->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <td colspan="8" class="text-end"><strong>Total:</strong></td>
                                    <td><strong id="total" class="text-primary"></strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="feather-check me-2"></i>Receive Stock
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            function calculateSubtotal(row) {
                var quantity = parseFloat(row.find('.quantity').val()) || 0;
                var price = parseFloat(row.find('.price').val()) || 0;
                var subtotal = quantity * price;
                row.find('.subtotal').val(subtotal.toFixed(2));
                calculateTotal();
            }

            function calculateTotal() {
                var total = 0;
                $('#purchaseProductsTable tr').each(function(){
                    var subtotal = parseFloat($(this).find('.subtotal').val()) || 0;
                    total += subtotal;
                });
                $('#total').text('$' + total.toFixed(2));
            }

            calculateTotal();

            $(document).on('input', '.quantity, .price', function(){
                var row = $(this).closest('tr');
                calculateSubtotal(row);
            });
        });
    </script>
@endpush
