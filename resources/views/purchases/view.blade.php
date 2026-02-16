@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Purchase Details</h1>
                    @can('add purchases')
                        <a href="{{ route('purchases.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Purchase</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                        <li class="breadcrumb-item active">Purchase Details</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('purchases.receive.stock', $purchase->id) }}" method="post">
            @csrf
            {{-- @method("PUT") --}}
            <div class="mb-3">
                <label for="purchase_number">Purchase Number: <span class="text-danger">*</span></label>
                <input type="text" id="purchase_number" name="purchase_number" value="{{ $purchase->purchase_number }}" class="form-control" placeholder="Purchase Number" readonly>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="supplier_id">Supplier: <span class="text-danger">*</span></label>
                    <input type="text" id="supplier_id" name="supplier_id" value="{{ (($purchase->supplier->last_name != '') ? $purchase->supplier->first_name . ' ' . $purchase->supplier->last_name : $purchase->supplier->first_name) }}" class="form-control" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id">Warehouse: <span class="text-danger">*</span></label>
                    <input type="text" id="warehouse_id" name="warehouse_id" value="{{ $purchase->warehouse->name }}" class="form-control" readonly>
                </div>
            </div>
            <div class="mb-3">
                <label for="purchase_note">Purchase Note:</label>
                <input type="text" id="purchase_note" name="purchase_note" value="{{ $purchase->purchase_note }}" class="form-control" readonly>
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-striped table-hover table-sm">
                    <thead>
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
                                    {{ $item->sku }}
                                    <input type="hidden" name="product_id[]" value="{{ $item->product_id }}">
                                </td>
                                <td>{{ $item->name }} - {{ $item->barcode }}</td>
                                <td><input type="text" name="quantity[]" value="{{ $item->quantity }}" class="form-control quantity" readonly></td>
                                <td>
                                    <span class="badge badge-secondary px-2 py-1" style="font-size:.9em;">
                                        {{ number_format((float) $item->previous_quantity, 2) }}
                                    </span>
                                </td>
                                <td><input type="text" name="price[]" value="{{ $item->price }}" class="form-control price" readonly></td>
                                <td>
                                    <span class="badge badge-info px-2 py-1" style="font-size:.9em;">
                                        {{ number_format((float) $item->avg_cost, 4) }}
                                    </span>
                                </td>
                                <td><input type="text" name="note[]" value="{{ $item->note }}" class="form-control note" readonly></td>
                                <td><input type="text" name="subtotal[]" value="{{ ($item->price * $item->quantity) }}" class="form-control subtotal" readonly></td>
                                <td>
                                    <select name="rack_id[]" id="rack_id" class="form-control">
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
                        <tr>
                            <td colspan="8"><strong>Total:</strong></td>
                            <td><strong id="total"></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Receive Stock</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            // Function to calculate subtotal for a row
            function calculateSubtotal(row) {
                var quantity = parseFloat(row.find('.quantity').val()) || 0;
                var price = parseFloat(row.find('.price').val()) || 0;
                var subtotal = quantity * price;
                row.find('.subtotal').val(subtotal.toFixed(2));
                calculateTotal();
            }

            // Function to calculate grand total
            function calculateTotal() {
                var total = 0;
                $('#purchaseProductsTable tr').each(function(){
                    var subtotal = parseFloat($(this).find('.subtotal').val()) || 0;
                    total += subtotal;
                });
                $('#total').text(total.toFixed(2));
            }

            // Calculate total on page load
            calculateTotal();

            // Event listener for quantity and price changes
            $(document).on('input', '.quantity, .price', function(){
                var row = $(this).closest('tr');
                calculateSubtotal(row);
            });

            $('#search_products').on('change, blur, input', function(){
                var search_products = $('#search_products').val();

                if (search_products != '') {
                    setTimeout(function() {
                        $.ajax({
                            url: `{{ route('products.search', ['query' => ':query']) }}`.replace(':query', search_products),
                            type: 'GET',
                            dataType: 'json',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(data) {
                                $('#search_products_list').empty('');
                                $.each(data, function(key, product){
                                    $('#search_products_list').append(`
                                        <li class="list-group-item search_product_item" data-id="${product.id}" data-sku="${product.sku}" data-barcode="${product.barcode}" data-name="${product.name}">${product.name} - ${product.barcode} - ${product.sku}</li>
                                    `);
                                });
                            }
                        });
                    }, 1000);
                }
            });

            $(document).on('click', '.search_product_item', function(){
                var product_id = $(this).data('id');
                var product_sku = $(this).data('sku');
                var product_barcode = $(this).data('barcode');
                var product_name = $(this).data('name');

                if (product_id != '' && product_sku != '' && product_barcode != '') {
                    length = $('#purchaseProductsTable tr').length;
                    $('#purchaseProductsTable').append(`
                        <tr>
                            <td>${ (length + 1) }</td>
                            <td>
                                ${ product_sku }
                                <input type="hidden" name="product_id[]" value="${ product_id }">
                            </td>
                            <td>${ product_name } - ${ product_barcode }</td>
                            <td><input type="text" name="quantity[]" value="1" class="form-control quantity"></td>
                            <td><input type="text" name="price[]" value="1" class="form-control price"></td>
                            <td><input type="text" name="note[]" value="" class="form-control note"></td>
                            <td><input type="text" name="subtotal[]" value="1" class="form-control subtotal"></td>
                            <td><button type="button" class="btn btn-danger btn-sm delete_product"><i class="fas fa-times"></i></button></td>
                        </tr>
                    `);
                    $('#search_products').val('');
                    calculateTotal();
                }
                $('#search_products_list').empty('');
            });

            $(document).on('click', '.delete_product', function(e){
                e.preventDefault();
                var row = $(this).closest('tr');
                row.remove();
                calculateSubtotal(row);
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .list-group-item {
            cursor: pointer;
        }
    </style>
@endpush
