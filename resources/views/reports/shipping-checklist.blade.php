@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header d-print-none">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Shipping Checklist</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></li>
                <li class="breadcrumb-item">Shipping Checklist</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <!-- Print Styles -->
    <style>
        @media print {
            /* Hide non-printable elements */
            .page-header, .sidebar, .navbar, .d-print-none,
            .card-header, form, .btn, .alert, footer,
            .nxl-navigation, .nxl-header, .page-sidebar {
                display: none !important;
            }

            /* Reset page margins */
            @page {
                size: landscape;
                margin: 0.5cm;
            }

            body {
                margin: 0;
                padding: 0;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .main-content, .content-wrapper, .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .card-body {
                padding: 0 !important;
            }

            /* Print header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 15px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }

            .print-header h2 {
                margin: 0;
                font-size: 18px;
                font-weight: bold;
            }

            .print-header p {
                margin: 5px 0 0;
                font-size: 12px;
                color: #666;
            }

            /* Table styles for print */
            .table {
                width: 100% !important;
                font-size: 9px !important;
                border-collapse: collapse !important;
            }

            .table th, .table td {
                padding: 3px 5px !important;
                border: 1px solid #333 !important;
                vertical-align: top !important;
            }

            .table th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
                text-align: center !important;
            }

            .table tbody tr:nth-child(even) {
                background-color: #fafafa !important;
            }

            /* Product image in print */
            .product-img-print {
                width: 35px !important;
                height: 35px !important;
                object-fit: contain !important;
            }

            /* Checkbox column */
            .checkbox-col {
                width: 20px !important;
                text-align: center !important;
            }

            .print-checkbox {
                width: 12px;
                height: 12px;
                border: 2px solid #333;
                display: inline-block;
            }

            /* Stock warning */
            .stock-warning {
                color: #dc3545 !important;
                font-weight: bold !important;
            }

            .stock-ok {
                color: #198754 !important;
            }

            /* Bundle component styles */
            .bundle-component {
                padding-left: 15px !important;
                font-size: 8px !important;
                color: #666 !important;
            }

            .component-arrow {
                margin-right: 3px;
            }

            /* Warehouse stock badges */
            .stock-badge {
                font-size: 8px !important;
                padding: 1px 3px !important;
                margin-bottom: 2px !important;
                display: inline-block !important;
            }
        }

        /* Screen styles */
        .print-header {
            display: none;
        }

        .product-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 4px;
        }

        .product-details {
            line-height: 1.4;
        }

        .product-details .sku {
            font-size: 0.85em;
            color: #666;
        }

        .product-details .dimensions {
            font-size: 0.8em;
            color: #888;
        }

        .stock-warning {
            color: #dc3545;
            font-weight: 600;
        }

        .stock-ok {
            color: #198754;
        }

        .bundle-component {
            padding-left: 20px;
            border-left: 2px solid #e0e0e0;
            margin-top: 8px;
            padding-top: 5px;
        }

        .bundle-component .component-item {
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #eee;
        }

        .bundle-component .component-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .component-arrow {
            color: #999;
            margin-right: 5px;
        }

        .warehouse-stock {
            font-size: 0.8em;
        }

        .warehouse-stock .badge {
            font-size: 0.75em;
            font-weight: normal;
        }
    </style>

    <!-- Filters (hidden when printing) -->
    <div class="card mb-4 d-print-none">
        <div class="card-header">
            <h5 class="card-title mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.shipping-checklist') }}" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sales Channel</label>
                    <select name="channel_id" class="form-select">
                        <option value="">All Channels</option>
                        @foreach($salesChannels as $channel)
                            <option value="{{ $channel->id }}" {{ $channelId == $channel->id ? 'selected' : '' }}>
                                {{ $channel->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- <div class="col-md-2 d-flex justify-content-end flex-column">
                    <label for="fulfilled" class="form-label">Order Status</label>
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="fulfilled" name="order_status" value="fulfilled" role="switch" {{ $order_status == 'fulfilled' ? 'checked' : '' }}>
                        <label for="fulfilled" class="form-check-label">Fulfilled</label>
                    </div>
                </div> --}}
                <div class="col-md-2">
                    <label class="form-label">Order Status</label>
                    <select name="order_status" class="form-select">
                        <option value="all" {{ $order_status == 'all' ? 'selected' : '' }}>All</option>
                        <option value="unfulfilleds" {{ $order_status == 'unfulfilled' ? 'selected' : '' }}>Unfulfilled</option>
                        <option value="fulfilled" {{ $order_status == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                    </select>
                </div>
                {{-- <div class="col-md-2">
                    <label class="form-label">Order Status</label>
                    <select name="order_status" class="form-select">
                        <option value="all" {{ $order_status == 'all' ? 'selected' : '' }}>All</option>
                        <option value="pending" {{ $order_status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ $order_status == 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="shipped" {{ $order_status == 'shipped' ? 'selected' : '' }}>Shipped</option>
                        <option value="delivered" {{ $order_status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                        <option value="awaiting_payment" {{ $order_status == 'awaiting_payment' ? 'selected' : '' }}>Awaiting Payment</option>
                        <option value="ready_for_pickup" {{ $order_status == 'ready_for_pickup' ? 'selected' : '' }}>Ready for Pickup</option>
                    </select>
                </div> --}}
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="feather-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('reports.shipping-checklist') }}" class="btn btn-outline-secondary">
                        <i class="feather-refresh-cw"></i>
                    </a>
                </div>
                <div class="col-md-2 d-flex align-items-end justify-content-end">
                    <a href="{{ route('reports.shipping-checklist.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'channel_id' => $channelId, 'order_status' => $order_status]) }}" class="btn btn-success">
                        <i class="feather-download me-1"></i> Download PDF
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards (hidden when printing) -->
    <div class="row mb-4 d-print-none">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Orders</h6>
                    <h3 class="mb-0">{{ $summary['total_orders'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Line Items</h6>
                    <h3 class="mb-0">{{ $summary['total_items'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title text-white-50">Total Quantity</h6>
                    <h3 class="mb-0">{{ $summary['total_quantity'] }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Checklist Table -->
    <div class="card">
        <div class="card-header d-print-none">
            <h5 class="card-title mb-0">Shipping Checklist</h5>
            <div class="ms-auto d-flex align-items-center gap-2">
                @php
                    $shippingChecklistColumns = [
                        ['key' => 'id', 'label' => '#', 'default' => true],
                        ['key' => 'order_id', 'label' => 'Order ID', 'default' => true],
                        ['key' => 'image', 'label' => 'Image', 'default' => false],
                        ['key' => 'product', 'label' => 'Product (SKU,Weight,Dimensions)', 'default' => true],
                        ['key' => 'sales_channel', 'label' => 'Sales Channel', 'default' => false],
                        ['key' => 'quantity', 'label' => 'Qty', 'default' => true],
                        ['key' => 'quantity_in_warehouse', 'label' => 'Qty in Warehouse', 'default' => true],
                        ['key' => 'tracking', 'label' => '', 'default' => true],
                    ];
                @endphp
                @include('partials.column-toggle', ['tableId' => 'shippingChecklistTable', 'cookieName' => 'shipping_checklist_columns', 'columns' => $shippingChecklistColumns])
            </div>
        </div>
        <div class="card-body">
            <!-- Print Header (only visible when printing) -->
            <div class="print-header">
                <div>
                    <h2>Shipping Checklist</h2>
                    <p>Date Range: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }} | Generated: {{ now()->format('M d, Y h:i A') }}</p>
                </div>
            </div>

            @if(count($checklistItems) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="shippingChecklistTable">
                        <thead class="table-light">
                            <tr>
                                {{-- <th class="checkbox-col" style="width: 30px;">
                                    <span class="d-none d-print-inline">&#9744;</span>
                                </th> --}}
                                <th data-column="id">#</th>
                                <th data-column="order_id" style="width: 130px;">Order ID</th>
                                <th data-column="image" style="width: 55px;">Image</th>
                                <th data-column="product">Product (SKU, Weight, Dimensions)</th>
                                <th data-column="sales_channel" style="width: 100px;">Sales Channel</th>
                                <th data-column="quantity" style="width: 60px; text-align: center;">Qty</th>
                                <th data-column="quantity_in_warehouse" style="width: 180px;">Qty in Warehouse</th>
                                <th data-column="tracking"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = ($checklistItems->currentPage() - 1) * $checklistItems->perPage() + 1;
                            @endphp
                            @foreach($checklistItems as $item)
                                <tr>
                                    <td data-column="id">{{ $i++; }}</td>
                                    {{-- <td class="checkbox-col text-center">
                                        <span class="print-checkbox d-none d-print-inline-block"></span>
                                        <input type="checkbox" class="form-check-input d-print-none" style="margin: 0;">
                                    </td> --}}
                                    <td data-column="order_id" class="text-center">
                                        <strong>{{ $item['ebay_order_id'] }}</strong>
                                    </td>
                                    <td data-column="image" class="text-center">
                                        @if($item['image_url'])
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['product_name'] }}"
                                                 class="product-img product-img-print">
                                        @else
                                            <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                                <i class="feather-image text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td data-column="product">
                                        <div class="product-details">
                                            <strong>{{ $item['product_name'] }}</strong>
                                            @if($item['is_bundle'])
                                                <span class="badge bg-soft-primary text-primary ms-1">Bundle</span>
                                            @endif
                                            @if($item['sku'])
                                                <div class="sku">SKU: {{ $item['sku'] }}</div>
                                            @endif
                                            <div class="dimensions">
                                                @if($item['weight'])
                                                    <span>Weight: {{ $item['weight'] }} {{ $item['weight_unit'] }}</span>
                                                @endif
                                                @if($item['length'] && $item['width'] && $item['height'])
                                                    @if($item['weight']) | @endif
                                                    <span>{{ $item['length'] }} x {{ $item['width'] }} x {{ $item['height'] }} {{ $item['dimension_unit'] }}</span>
                                                @endif
                                                @if(!$item['weight'] && !$item['length'])
                                                    <span class="text-muted">No dimensions</span>
                                                @endif
                                            </div>

                                            {{-- Bundle Components --}}
                                            @if($item['is_bundle'] && !empty($item['components']))
                                                <div class="bundle-component">
                                                    @foreach($item['components'] as $component)
                                                        <div class="component-item">
                                                            <i class="feather-corner-down-right component-arrow"></i>
                                                            <strong>{{ $component['product_name'] }}</strong>
                                                            @if($component['sku'])
                                                                <span class="text-muted">({{ $component['sku'] }})</span>
                                                            @endif
                                                            <div class="ms-4">
                                                                <small class="text-muted">
                                                                    @if($component['weight'])
                                                                        Weight: {{ $component['weight'] }} {{ $component['weight_unit'] }}
                                                                    @endif
                                                                    @if($component['length'] && $component['width'] && $component['height'])
                                                                        @if($component['weight']) | @endif
                                                                        {{ $component['length'] }} x {{ $component['width'] }} x {{ $component['height'] }} {{ $component['dimension_unit'] }}
                                                                    @endif
                                                                </small>
                                                                <div class="warehouse-stock mt-1">
                                                                    <small><strong>Qty: {{ $component['quantity_ordered'] }}</strong></small>
                                                                    @if(!empty($component['warehouse_stocks']))
                                                                        <span class="ms-2">|</span>
                                                                        @foreach($component['warehouse_stocks'] as $stock)
                                                                            <span class="badge bg-soft-info text-info stock-badge">{{ $stock['warehouse'] }}</span>
                                                                            <span class="badge bg-soft-secondary text-secondary stock-badge">{{ $stock['rack'] }}</span>
                                                                            <span class="{{ $stock['quantity'] < $component['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">({{ $stock['quantity'] }})</span>
                                                                        @endforeach
                                                                    @else
                                                                        <span class="text-muted ms-2">No stock</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-column="sales_channel">{{ $item['sales_channel'] }}</td>
                                    <td data-column="quantity" class="text-center">
                                        <strong>{{ $item['quantity_ordered'] }}</strong>
                                    </td>
                                    <td data-column="quantity_in_warehouse">
                                        @if($item['is_bundle'])
                                            <span class="text-muted fs-11">See components</span>
                                        @elseif(!empty($item['warehouse_stocks']))
                                            <div class="warehouse-stock">
                                                @foreach($item['warehouse_stocks'] as $stock)
                                                    <div class="mb-1">
                                                        <span class="badge bg-soft-info text-info">{{ $stock['warehouse'] }}</span>
                                                        <span class="badge bg-soft-secondary text-secondary">{{ $stock['rack'] }}</span>
                                                        <span class="{{ $stock['quantity'] < $item['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">({{ $stock['quantity'] }})</span>
                                                    </div>
                                                @endforeach
                                                <div class="mt-1 pt-1 border-top">
                                                    <strong class="{{ $item['total_stock'] < $item['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">
                                                        Total: {{ $item['total_stock'] }}
                                                    </strong>
                                                    @if($item['total_stock'] < $item['quantity_ordered'])
                                                        <i class="feather-alert-triangle text-danger ms-1 d-print-none" title="Low stock"></i>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="stock-warning">No stock</span>
                                        @endif
                                    </td>
                                    <td data-column="tracking">
                                        <span style="white-space: normal; width: 250px; display: block;">
                                            @php
                                                $allTrackingNumbers = $item['order']->getAllTrackingNumbers();
                                            @endphp
                                            @if (count($allTrackingNumbers) > 0 || $item['order']->tracking_number)
                                                @if(count($allTrackingNumbers) > 1)
                                                    {{-- Multi-package tracking --}}
                                                    @foreach($allTrackingNumbers as $index => $pkg)
                                                        <div class="mb-1">
                                                            <span class="badge bg-soft-info text-info">Package {{ $index + 1 }}</span>
                                                            <strong>{{ $pkg['carrier'] ?? $item['order']->shipping_carrier }}</strong>:
                                                            @if($item['order']->tracking_url)
                                                                <a href="{{ $item['order']->tracking_url }}{{ $pkg['tracking_number'] }}" target="_blank" class="text-primary">
                                                                    {{ $pkg['tracking_number'] }} <i class="feather-external-link fs-10"></i>
                                                                </a>
                                                            @else
                                                                {{ $pkg['tracking_number'] }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @elseif($item['order']->tracking_number)
                                                    {{-- Single tracking --}}
                                                    <strong>{{ $item['order']->shipping_carrier }}</strong>:
                                                    @if($item['order']->tracking_url)
                                                        <a href="{{ $item['order']->tracking_url }}" target="_blank" class="text-primary">
                                                            {{ $item['order']->tracking_number }} <i class="feather-external-link fs-10"></i>
                                                        </a>
                                                    @else
                                                        {{ $item['order']->tracking_number }}
                                                    @endif
                                                @endif
                                            @else
                                                @php
                                                    $deadlineStatus = $item['order']->getShipmentDeadlineStatus();
                                                    $deadlineColors = [
                                                        'overdue' => 'danger',
                                                        'urgent' => 'warning',
                                                        'upcoming' => 'info',
                                                        'ok' => 'success',
                                                    ];
                                                    $deadlineLabels = [
                                                        'overdue' => 'OVERDUE',
                                                        'urgent' => 'URGENT',
                                                        'upcoming' => 'Soon',
                                                        'ok' => '',
                                                    ];
                                                @endphp
                                                @if($item['order']->shipment_deadline && $deadlineStatus)
                                                    <span class="fs-12 {{ $deadlineStatus === 'overdue' ? 'text-danger fw-bold' : ($deadlineStatus === 'urgent' ? 'text-warning fw-semibold' : '') }}">
                                                        {{ $item['order']->shipment_deadline->format('d M, Y') }}
                                                    </span>
                                                    @if($deadlineLabels[$deadlineStatus])
                                                        <span class="d-block">
                                                            <span class="badge bg-soft-{{ $deadlineColors[$deadlineStatus] }} text-{{ $deadlineColors[$deadlineStatus] }} fs-10">
                                                                {{ $deadlineLabels[$deadlineStatus] }}
                                                            </span>
                                                        </span>
                                                    @else
                                                        <span class="d-block fs-11 text-muted">{{ $item['order']->shipment_deadline->diffForHumans() }}</span>
                                                    @endif
                                                @elseif($item['order']->shipment_deadline)
                                                    <span class="fs-12 text-muted">{{ $item['order']->shipment_deadline->format('d M, Y') }}</span>
                                                @else
                                                    <span class="text-muted fs-12">-</span>
                                                @endif
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="feather-info me-2"></i>
                    No orders found matching the selected criteria.
                </div>
            @endif

            @if(count($checklistItems) > 0)
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        Showing {{ $checklistItems->firstItem() }} to {{ $checklistItems->lastItem() }} of {{ $checklistItems->total() }} items
                    </div>
                    <div>
                        {{ $checklistItems->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
