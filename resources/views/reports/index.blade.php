@extends('layouts.app')

@section('header')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Accounting Reports</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <!-- Trial Balance -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.trial-balance') }}" class="text-decoration-none">
                <div class="card mb-4 border-left-primary">
                    <div class="card-body text-right py-4">
                        <i class="fas fa-balance-scale fa-3x text-primary mb-3"></i>
                        <h5 class="card-title mb-1">Trial Balance</h5>
                        <p class="text-muted text-left small mb-0">View debit & credit balances for all accounts</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Expense Report -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.expense') }}" class="text-decoration-none">
                <div class="card mb-4 border-left-danger">
                    <div class="card-body text-right py-4">
                        <i class="fas fa-chart-pie fa-3x text-danger mb-3"></i>
                        <h5 class="card-title mb-1">Expense Report</h5>
                        <p class="text-muted text-left small mb-0">Expense breakdown by category & period</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Supplier Ledger -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.supplier-ledger') }}" class="text-decoration-none">
                <div class="card mb-4 border-left-warning">
                    <div class="card-body text-right py-4">
                        <i class="fas fa-user-tie fa-3x text-warning mb-3"></i>
                        <h5 class="card-title mb-1">Supplier Ledger</h5>
                        <p class="text-muted text-left small mb-0">Bill & payment history per supplier</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Bank & Cash Summary -->
        <div class="col-lg-3 col-md-6">
            <a href="{{ route('reports.bank-summary') }}" class="text-decoration-none">
                <div class="card mb-4 border-left-success">
                    <div class="card-body text-right py-4">
                        <i class="fas fa-university fa-3x text-success mb-3"></i>
                        <h5 class="card-title mb-1">Bank & Cash Summary</h5>
                        <p class="text-muted text-left small mb-0">Inflows, outflows & balances for all accounts</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
@endsection