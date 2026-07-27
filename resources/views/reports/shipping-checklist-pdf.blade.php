<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Checklist</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.4;
            color: #333;
        }

        /* Page setup */
        @page {
            size: landscape;
            margin: 1cm;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10px;
            color: #666;
        }

        /* Summary cards */
        .summary {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .summary-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 8px;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }

        .summary-item .label {
            font-size: 9px;
            color: #666;
            margin-bottom: 3px;
        }

        .summary-item .value {
            font-size: 14px;
            font-weight: bold;
        }

        /* Table styles */
        .table-container {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }

        th {
            background-color: #e9e9e9;
            font-weight: bold;
            text-align: left;
            padding: 6px 4px;
            border: 1px solid #333;
            font-size: 9px;
        }

        th.center {
            text-align: center;
        }

        td {
            padding: 5px 4px;
            border: 1px solid #333;
            vertical-align: top;
        }

        /* CRITICAL: Prevent row from breaking across pages */
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Column widths */
        .col-num { width: 4%; text-align: center; }
        .col-order { width: 12%; }
        .col-image { width: 6%; text-align: center; }
        .col-product { width: 32%; }
        .col-channel { width: 10%; }
        .col-qty { width: 5%; text-align: center; }
        .col-stock { width: 16%; }
        .col-tracking { width: 15%; }

        /* Product details */
        .product-name {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .bundle-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 1px 4px;
            font-size: 7px;
            border-radius: 2px;
            margin-left: 3px;
        }

        .sku {
            font-size: 8px;
            color: #666;
            margin-bottom: 2px;
        }

        .dimensions {
            font-size: 8px;
            color: #888;
        }

        /* Bundle components */
        .bundle-components {
            margin-top: 8px;
            padding-left: 10px;
            border-left: 2px solid #ccc;
        }

        .component-item {
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 1px dashed #ddd;
        }

        .component-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .component-arrow {
            color: #999;
            margin-right: 3px;
        }

        .component-name {
            font-weight: bold;
        }

        .component-details {
            font-size: 8px;
            color: #666;
            margin-left: 12px;
        }

        /* Stock display */
        .stock-item {
            margin-bottom: 3px;
        }

        .warehouse-badge {
            background: #e8f4f8;
            color: #0277bd;
            padding: 1px 3px;
            font-size: 7px;
            border-radius: 2px;
        }

        .rack-badge {
            background: #f5f5f5;
            color: #555;
            padding: 1px 3px;
            font-size: 7px;
            border-radius: 2px;
        }

        .stock-ok {
            color: #2e7d32;
            font-weight: bold;
        }

        .stock-warning {
            color: #c62828;
            font-weight: bold;
        }

        .stock-total {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px solid #ddd;
            font-weight: bold;
        }

        /* Image placeholder */
        .product-img {
            width: 35px;
            height: 35px;
            object-fit: contain;
        }

        .no-image {
            width: 35px;
            height: 35px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 8px;
        }

        /* Tracking info */
        .tracking-number {
            font-size: 8px;
            word-break: break-all;
        }

        .deadline-overdue {
            color: #c62828;
            font-weight: bold;
        }

        .deadline-urgent {
            color: #ef6c00;
            font-weight: bold;
        }

        .deadline-badge {
            display: inline-block;
            padding: 1px 4px;
            font-size: 7px;
            border-radius: 2px;
            margin-top: 2px;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        /* Checkbox */
        .checkbox {
            width: 12px;
            height: 12px;
            border: 2px solid #333;
            display: inline-block;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
            text-align: center;
        }

        /* No data message */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Shipping Checklist</h1>
        <p>Date Range: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }} | Generated: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    <!-- Summary -->
    <div class="summary">
        <div class="summary-item">
            <div class="label">Total Orders</div>
            <div class="value">{{ $summary['total_orders'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Line Items</div>
            <div class="value">{{ $summary['total_items'] }}</div>
        </div>
        <div class="summary-item">
            <div class="label">Total Quantity</div>
            <div class="value">{{ $summary['total_quantity'] }}</div>
        </div>
    </div>

    <!-- Table -->
    @if(count($checklistItems) > 0)
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="col-num center">#</th>
                        <th class="col-order">Order ID</th>
                        <th class="col-image center">Image</th>
                        <th class="col-product">Product (SKU, Weight, Dimensions)</th>
                        <th class="col-channel">Sales Channel</th>
                        <th class="col-qty center">Qty</th>
                        <th class="col-stock">Qty in Warehouse</th>
                        <th class="col-tracking">Tracking / Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @php $i = 1; @endphp
                    @foreach($checklistItems as $item)
                        <tr>
                            <td class="col-num">{{ $i++ }}</td>
                            <td class="col-order">
                                <strong>{{ $item['ebay_order_id'] }}</strong>
                            </td>
                            <td class="col-image">
                                @if($item['image_url'])
                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['product_name'] }}" class="product-img">
                                @else
                                    <div class="no-image">No Img</div>
                                @endif
                            </td>
                            <td class="col-product">
                                <div class="product-name">
                                    {{ $item['product_name'] }}
                                    @if($item['is_bundle'])
                                        <span class="bundle-badge">Bundle</span>
                                    @endif
                                </div>
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
                                        <span style="color: #999;">No dimensions</span>
                                    @endif
                                </div>

                                {{-- Bundle Components --}}
                                @if($item['is_bundle'] && !empty($item['components']))
                                    <div class="bundle-components">
                                        @foreach($item['components'] as $component)
                                            <div class="component-item">
                                                <span class="component-arrow">&#8627;</span>
                                                <span class="component-name">{{ $component['product_name'] }}</span>
                                                @if($component['sku'])
                                                    <span style="color: #666;">({{ $component['sku'] }})</span>
                                                @endif
                                                <div class="component-details">
                                                    @if($component['weight'])
                                                        Weight: {{ $component['weight'] }} {{ $component['weight_unit'] }}
                                                    @endif
                                                    @if($component['length'] && $component['width'] && $component['height'])
                                                        @if($component['weight']) | @endif
                                                        {{ $component['length'] }} x {{ $component['width'] }} x {{ $component['height'] }} {{ $component['dimension_unit'] }}
                                                    @endif
                                                    <div style="margin-top: 2px;">
                                                        <strong>Qty: {{ $component['quantity_ordered'] }}</strong>
                                                        @if(!empty($component['warehouse_stocks']))
                                                            |
                                                            @foreach($component['warehouse_stocks'] as $stock)
                                                                <span class="warehouse-badge">{{ $stock['warehouse'] }}</span>
                                                                <span class="rack-badge">{{ $stock['rack'] }}</span>
                                                                <span class="{{ $stock['quantity'] < $component['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">({{ $stock['quantity'] }})</span>
                                                            @endforeach
                                                        @else
                                                            <span style="color: #999;">No stock</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="col-channel">{{ $item['sales_channel'] }}</td>
                            <td class="col-qty"><strong>{{ $item['quantity_ordered'] }}</strong></td>
                            <td class="col-stock">
                                @if($item['is_bundle'])
                                    <span style="color: #666; font-size: 8px;">See components</span>
                                @elseif(!empty($item['warehouse_stocks']))
                                    @foreach($item['warehouse_stocks'] as $stock)
                                        <div class="stock-item">
                                            <span class="warehouse-badge">{{ $stock['warehouse'] }}</span>
                                            <span class="rack-badge">{{ $stock['rack'] }}</span>
                                            <span class="{{ $stock['quantity'] < $item['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">({{ $stock['quantity'] }})</span>
                                        </div>
                                    @endforeach
                                    <div class="stock-total {{ $item['total_stock'] < $item['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">
                                        Total: {{ $item['total_stock'] }}
                                    </div>
                                @else
                                    <span class="stock-warning">No stock</span>
                                @endif
                            </td>
                            <td class="col-tracking">
                                @php
                                    $allTrackingNumbers = $item['order']->getAllTrackingNumbers();
                                @endphp
                                @if (count($allTrackingNumbers) > 0 || $item['order']->tracking_number)
                                    @if(count($allTrackingNumbers) > 1)
                                        {{-- Multi-package tracking --}}
                                        @foreach($allTrackingNumbers as $index => $pkg)
                                            <div class="tracking-number" style="margin-bottom: 3px;">
                                                <span class="warehouse-badge">Pkg {{ $index + 1 }}</span>
                                                <strong>{{ $pkg['carrier'] ?? $item['order']->shipping_carrier }}</strong>:
                                                {{ $pkg['tracking_number'] }}
                                            </div>
                                        @endforeach
                                    @elseif($item['order']->tracking_number)
                                        {{-- Single tracking --}}
                                        <div class="tracking-number">
                                            <strong>{{ $item['order']->shipping_carrier }}</strong>:
                                            {{ $item['order']->tracking_number }}
                                        </div>
                                    @endif
                                @else
                                    @php
                                        $deadlineStatus = $item['order']->getShipmentDeadlineStatus();
                                    @endphp
                                    @if($item['order']->shipment_deadline && $deadlineStatus)
                                        <span class="{{ $deadlineStatus === 'overdue' ? 'deadline-overdue' : ($deadlineStatus === 'urgent' ? 'deadline-urgent' : '') }}">
                                            {{ $item['order']->shipment_deadline->format('d M, Y') }}
                                        </span>
                                        @if($deadlineStatus === 'overdue')
                                            <div><span class="deadline-badge badge-danger">OVERDUE</span></div>
                                        @elseif($deadlineStatus === 'urgent')
                                            <div><span class="deadline-badge badge-warning">URGENT</span></div>
                                        @elseif($deadlineStatus === 'upcoming')
                                            <div style="font-size: 8px; color: #666;">Soon</div>
                                        @else
                                            <div style="font-size: 8px; color: #666;">{{ $item['order']->shipment_deadline->diffForHumans() }}</div>
                                        @endif
                                    @elseif($item['order']->shipment_deadline)
                                        <span style="color: #666;">{{ $item['order']->shipment_deadline->format('d M, Y') }}</span>
                                    @else
                                        <span style="color: #999;">-</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="no-data">
            No orders found matching the selected criteria.
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        Shipping Checklist - Generated on {{ now()->format('F d, Y h:i A') }}
    </div>
</body>
</html>
