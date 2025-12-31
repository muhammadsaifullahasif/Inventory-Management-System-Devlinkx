@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Purchase Edit</h1>
                    @can('add purchases')
                        <a href="{{ route('purchases.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Purchase</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
                        <li class="breadcrumb-item active">Purchase Edit</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body">
        <form action="{{ route('purchases.update', $purchase->id) }}" method="post">
            @csrf
            @method("PUT")
            <div class="mb-3">
                <label for="purchase_number">Purchase Number: <span class="text-danger">*</span></label>
                <input type="text" id="purchase_number" name="purchase_number" value="{{ old('purchase_number', $purchase->purchase_number) }}" class="form-control @error('purchase_number') is-invalid @enderror" placeholder="Purchase Number">
                @error('purchase_number')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="supplier_id">Supplier: <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror">
                        <option value="">Select Supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (old('supplier_id', $purchase->supplier_id) == $supplier->id) ? 'selected' : '' }}>{{ (($supplier->last_name != '') ? $supplier->first_name . ' ' . $supplier->last_name : $supplier->first_name) }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id">Warehouse: <span class="text-danger">*</span></label>
                    <select name="warehouse_id" id="warehouse_id" class="form-control @error('warehouse_id') is-invalid @enderror">
                        <option value="">Select Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (old('warehouse_id', $purchase->warehouse_id) == $warehouse->id) ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            <div class="mb-3">
                <label for="purchase_note">Purchase Note:</label>
                <input type="text" id="purchase_note" name="purchase_note" value="{{ old('purchase_note', $purchase->purchase_note) }}" class="form-control @error('purchase_note') is-invalid @enderror">
                @error('purchase_note')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <label for="search_products">Search Products</label>
                <input type="search" id="search_products" name="search_products" class="form-control" placeholder="Search products by Name, SKU, Barcode">
                <div id="search_products_result">
                    <ul id="search_products_list" class="list-group"></ul>
                </div>
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Rack</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Note</th>
                            <th>Sub Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="purchaseProductsTable">
                        @foreach ($purchase->purchase_items as $item)
                            <tr>
                                <td>{{ ($loop->index + 1) }}</td>
                                <td>
                                    {{ $item->sku }}
                                    <input type="hidden" name="purchase_item_id[]" value="{{ $item->id }}">
                                    <input type="hidden" name="product_id[]" value="{{ $item->product_id }}">
                                </td>
                                <td>{{ $item->name }} - {{ $item->barcode }}</td>
                                <td>
                                    <select class="form-control rack" name="rack[]">
                                        <option value="">Select Rack</option>
                                        @foreach ($warehouse->racks as $rack)
                                            <option value="{{ $rack->id }}"
                                                @if ($rack->id == $item->rack_id || $rack->is_default == '1')
                                                    selected
                                                @endif
                                                >
                                                {{ $rack->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="quantity[]" value="{{ $item->quantity }}" class="form-control quantity"></td>
                                <td><input type="text" name="price[]" value="{{ $item->price }}" class="form-control price"></td>
                                <td><input type="text" name="note[]" value="{{ $item->note }}" class="form-control note"></td>
                                <td><input type="text" name="subtotal[]" value="{{ ($item->price * $item->quantity) }}" class="form-control subtotal"></td>
                                <td><button type="button" class="btn btn-danger btn-sm delete_product"><i class="fas fa-times"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td><strong>Total:</strong></td>
                            <td><strong id="total"></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            // Empty array to store racks
            let racks = [];

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

            // Function to populate rack select options
            function populateRackOptions(selectElement, selectedRackId = null) {
                selectElement.empty();
                selectElement.append('<option value="">Select Rack</option>');

                var defaultRackId = null;

                $.each(racks, function(key, rack){
                    selectElement.append(`<option value="${rack.id}">${rack.name}</option>`);

                    // Find default rack
                    if (rack.is_default == 1 || rack.is_default == '1') {
                        defaultRackId = rack.id;
                    }
                });

                // Set selected value
                if (selectedRackId) {
                    selectElement.val(selectedRackId);
                } else if (defaultRackId) {
                    selectElement.val(defaultRackId);
                }
            }

            // Function to update all rack selects in the table
            function updateAllRAckSelects() {
                $('#purchaseProductsTable tr').each(function(){
                    var rackSelect = $(this).find('.rack');
                    var selectedValue = rackSelect.val(); // Store current selection
                    populateRackOptions(rackSelect);
                    rackSelect.val(selectedValue); // Restore selection if it exists
                });
            }

            // Load racks for the current warehouse on page load
            var initialWarehouseId = $('#warehouse_id').val();
            if (initialWarehouseId) {
                $.ajax({
                    url: `{{ route('warehouses.racks', ['warehouse' => ':id']) }}`.replace(':id', initialWarehouseId),
                    type: 'GET',
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(data) {
                        racks = data;
                        updateAllRAckSelects();
                        calculateTotal(); // Calculate total on page load
                    }
                });
            }

            // Event listener for warehouse change
            $('#warehouse_id').on('change', function(){
                var warehouse_id = $(this).val();

                if (warehouse_id != '') {
                    $.ajax({
                        url: `{{ route('warehouses.racks', ['warehouse', ':id']) }}`.replace(':id', warehouse_id),
                        type: 'GET',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(data) {
                            racks = data; // Update racks array with new data
                            updateAllRackSelects(); // Update all existing rack selects
                        }
                    });
                } else {
                    racks = []; // Clear racks if no warehouse selected
                    updateAllRackSelects();
                }
            });

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

                    var newRow = $(`
                        <tr>
                            <td>${ (length + 1) }</td>
                            <td>
                                ${ product_sku }
                                <input type="hidden" name="product_id[]" value="${ product_id }">
                            </td>
                            <td>${ product_name } - ${ product_barcode }</td>
                            <td>
                                <select class="form-control rack" name="rack[]">
                                    <option value="">Select Rack</option>
                                </select>
                            </td>
                            <td><input type="text" name="quantity[]" value="1" class="form-control quantity"></td>
                            <td><input type="text" name="price[]" value="1" class="form-control price"></td>
                            <td><input type="text" name="note[]" value="" class="form-control note"></td>
                            <td><input type="text" name="subtotal[]" value="1" class="form-control subtotal"></td>
                            <td><button type="button" class="btn btn-danger btn-sm delete_product"><i class="fas fa-times"></i></button></td>
                        </tr>
                    `);

                    $('#purchaseProductsTable').append(newRow);

                    // Populate rack options for the new row
                    populateRackOptions(newRow.find('.rack'));

                    $('#search_products').val('');
                    $('#search_products_list').empty('');
                    calculateTotal();
                }
                // $('#search_products_list').empty('');
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
