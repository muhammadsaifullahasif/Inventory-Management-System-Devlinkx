@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Print Rack Label</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                <li class="breadcrumb-item">Print Label</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('racks.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Racks</span>
                    </a>
                    <a href="{{ route('racks.label.bulk-form') }}" class="btn btn-primary">
                        <i class="feather-layers me-2"></i>
                        <span>Bulk Print</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-info me-2"></i>Rack Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Rack Name</div>
                        <div class="col-sm-8"><span class="fw-semibold">{{ $rack->name }}</span></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Warehouse</div>
                        <div class="col-sm-8">{{ $rack->warehouse->name }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Rack ID</div>
                        <div class="col-sm-8">{{ $rack->id }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="feather-settings me-2"></i>Print Settings</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('racks.label.print', $rack->id) }}" id="printForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Number of Labels</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" value="21" min="1" max="100">
                                <small class="text-muted">1-100 labels</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="columns" class="form-label">Columns per Row</label>
                                <select id="columns" name="columns" class="form-select">
                                    <option value="2" selected>2 Columns</option>
                                    <option value="3">3 Columns</option>
                                    <option value="4">4 Columns</option>
                                    <option value="5">5 Columns</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-printer me-2"></i>Print Labels
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Label Preview -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><i class="feather-eye me-2"></i>Label Preview</h5>
            </div>
            <div class="card-body text-center">
                <div style="display: inline-block; border: 1px solid #000; padding: 20px 30px; border-radius: 4px;">
                    <div style="font-size: 22px; font-weight: bold;">{{ $rack->name }}</div>
                    <div style="margin-top: 5px; font-size: 12px; color: #555;">{{ $rack->warehouse->name }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
