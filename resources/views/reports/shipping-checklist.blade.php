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
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
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
                font-size: 10px !important;
                border-collapse: collapse !important;
            }

            .table th, .table td {
                padding: 4px 6px !important;
                border: 1px solid #333 !important;
                vertical-align: middle !important;
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
                width: 40px !important;
                height: 40px !important;
                object-fit: contain !important;
            }

            /* Checkbox column */
            .checkbox-col {
                width: 25px !important;
                text-align: center !important;
            }

            .print-checkbox {
                width: 14px;
                height: 14px;
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
                <div class="col-md-2">
                    <label class="form-label">Fulfillment Status</label>
                    <select name="fulfillment_status" class="form-select">
                        <option value="unfulfilled" {{ $fulfillmentStatus == 'unfulfilled' ? 'selected' : '' }}>Unfulfilled</option>
                        <option value="fulfilled" {{ $fulfillmentStatus == 'fulfilled' ? 'selected' : '' }}>Fulfilled</option>
                        <option value="all" {{ $fulfillmentStatus == 'all' ? 'selected' : '' }}>All</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="feather-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('reports.shipping-checklist') }}" class="btn btn-outline-secondary">
                        <i class="feather-refresh-cw"></i>
                    </a>
                </div>
                <div class="col-md-2 d-flex align-items-end justify-content-end">
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="feather-printer me-1"></i> Print Checklist
                    </button>
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
        </div>
        <div class="card-body">
            <!-- Print Header (only visible when printing) -->
            <div class="print-header">
                <h2>Shipping Checklist</h2>
                <p>Date Range: {{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }} | Generated: {{ now()->format('M d, Y h:i A') }}</p>
            </div>

            @if(count($checklistItems) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="checkbox-col d-print-table-cell" style="width: 30px;">
                                    <span class="d-none d-print-inline">&#9744;</span>
                                </th>
                                <th style="width: 140px;">Order ID</th>
                                <th style="width: 60px;">Image</th>
                                <th>Product (SKU, Weight, Dimensions)</th>
                                <th style="width: 120px;">Sales Channel</th>
                                <th style="width: 80px; text-align: center;">Qty Ordered</th>
                                <th style="width: 80px; text-align: center;">Qty in Warehouse</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($checklistItems as $item)
                                <tr>
                                    <td class="checkbox-col text-center">
                                        <span class="print-checkbox d-none d-print-inline-block"></span>
                                        <input type="checkbox" class="form-check-input d-print-none" style="margin: 0;">
                                    </td>
                                    <td>
                                        <strong>{{ $item['ebay_order_id'] }}</strong>
                                    </td>
                                    <td class="text-center">
                                        @if($item['image_url'])
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['product_name'] }}"
                                                 class="product-img product-img-print">
                                        @else
                                            <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                                <i class="feather-image text-muted"></i>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="product-details">
                                            <strong>{{ $item['product_name'] }}</strong>
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
                                        </div>
                                    </td>
                                    <td>{{ $item['sales_channel'] }}</td>
                                    <td class="text-center">
                                        <strong>{{ $item['quantity_ordered'] }}</strong>
                                    </td>
                                    <td class="text-center {{ $item['quantity_in_warehouse'] < $item['quantity_ordered'] ? 'stock-warning' : 'stock-ok' }}">
                                        {{ $item['quantity_in_warehouse'] }}
                                        @if($item['quantity_in_warehouse'] < $item['quantity_ordered'])
                                            <i class="feather-alert-triangle d-print-none" title="Low stock"></i>
                                        @endif
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
        </div>
    </div>
@endsection
