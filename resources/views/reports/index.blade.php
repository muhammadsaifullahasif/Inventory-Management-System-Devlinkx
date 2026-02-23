@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Accounting Reports</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item">Reports</li>
            </ul>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
    <div class="row">
        <!-- Trial Balance -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.trial-balance') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-circle mx-auto mb-3">
                            <i class="feather-bar-chart-2" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Trial Balance</h5>
                        <p class="text-muted small mb-0">View debit & credit balances for all accounts</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Expense Report -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.expense') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-3">
                            <i class="feather-pie-chart" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Expense Report</h5>
                        <p class="text-muted small mb-0">Expense breakdown by category & period</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Supplier Ledger -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.supplier-ledger') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-warning text-warning rounded-circle mx-auto mb-3">
                            <i class="feather-users" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Supplier Ledger</h5>
                        <p class="text-muted small mb-0">Bill & payment history per supplier</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Bank & Cash Summary -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.bank-summary') }}" class="text-decoration-none">
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-text avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-3">
                            <i class="feather-credit-card" style="font-size: 1.5rem;"></i>
                        </div>
                        <h5 class="card-title mb-1 fw-semibold">Bank & Cash Summary</h5>
                        <p class="text-muted small mb-0">Inflows, outflows & balances for all accounts</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection
