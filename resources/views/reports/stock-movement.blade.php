@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Stock Movement</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item">Stock Movement</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">

        <!-- Out of Stock Items -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.out-of-stock') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-3">
                            <i class="feather-alert-circle" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Out of Stock Items</h5>
                        <p class="text-muted small mb-0">Products with zero or low stock levels</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Slow Moving Items -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.slow-moving-items') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-warning text-warning rounded-circle mx-auto mb-3">
                            <i class="feather-trending-down" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Slow Moving Items</h5>
                        <p class="text-muted small mb-0">Products with low sales velocity</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Frequently Ordered Items -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.frequently-ordered-items') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-3">
                            <i class="feather-star" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Frequently Ordered Items</h5>
                        <p class="text-muted small mb-0">Top selling products by quantity</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection
