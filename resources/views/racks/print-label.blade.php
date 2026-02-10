@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Print Rack Label</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('racks.index') }}">Racks</a></li>
                        <li class="breadcrumb-item active">Print Label</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Rack Details</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%;">Rack Name</th>
                            <td>{{ $rack->name }}</td>
                        </tr>
                        <tr>
                            <th>Warehouse</th>
                            <td>{{ $rack->warehouse->name }}</td>
                        </tr>
                        <tr>
                            <th>Rack ID</th>
                            <td>{{ $rack->id }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Print Settings</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('racks.label.print', $rack->id) }}" id="printForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="quantity">Number of Labels:</label>
                                    <input type="number" id="quantity" name="quantity" class="form-control" value="21" min="1" max="100">
                                    <small class="text-muted">1-100 labels</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="columns">Columns per Row:</label>
                                    <select id="columns" name="columns" class="form-control">
                                        <option value="2" selected>2 Columns</option>
                                        <option value="3">3 Columns</option>
                                        <option value="4">4 Columns</option>
                                        <option value="5">5 Columns</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Labels
                            </button>
                            <a href="{{ route('racks.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Racks
                            </a>
                            <a href="{{ route('racks.label.bulk-form') }}" class="btn btn-info">
                                <i class="fas fa-layer-group"></i> Bulk Print
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Label Preview -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Label Preview</h3>
        </div>
        <div class="card-body text-center">
            <div style="display: inline-block; border: 1px solid #000; padding: 20px 30px;">
                <div style="font-size: 22px; font-weight: bold;">{{ $rack->name }}</div>
                <div style="margin-top: 5px; font-size: 12px; color: #555;">{{ $rack->warehouse->name }}</div>
            </div>
        </div>
    </div>
@endsection
