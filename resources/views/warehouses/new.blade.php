@extends('layouts.app')

@section('header')
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 d-inline mr-2">Warehouse New</h1>
                    @can('add warehouses')
                        <a href="{{ route('warehouses.create') }}" class="btn btn-outline-primary btn-sm mb-3">Add Warehouse</a>
                    @endcan
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Warehouses</a></li>
                        <li class="breadcrumb-item active">Warehouse New</li>
                    </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
@endsection

@section('content')
    <div class="card card-body w-50">
        <form action="{{ route('warehouses.store') }}" method="post">
            @csrf
            <div class="mb-3">
                <label for="name">Name: <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Warehouse Name">
                @error('name')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div class="mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" id="is_default" value="1" name="is_default" class="custom-control-input" {{ (old('is_default') == 1) ? 'checked' : '' }}>
                    <label for="is_default" class="custom-control-label">Is Default</label>
                </div>
            </div>
            <div class="mb-3">
                <label for="racks">No of Racks:</label>
                <input type="text" id="racks" name="racks" value="{{ old('racks', 1) }}" class="form-control" placeholder="Number of Racks in Warehouse">
                @error('racks')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
            </div>
            <div id="racksCountContainer" class="table-responsive">
                <table class="table table-striped table-hover table-sm">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Name</th>
                        </tr>
                    </thead>
                    <tbody id="racksCountTable">
                        <tr>
                            <td>1</td>
                            <td><input type="text" name="rack[]" value="Rack-1" class="form-control form-control-sm" placeholder="Rack Name"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            // Function to update table rows based on racks count
            function updateRacksTable(count) {
                var currentRows = $('#racksCountTable tr').length;
                var racksCount = parseInt(count) || 0;

                if (racksCount < 0) racksCount = 0;

                // If new count is greater, add rows
                if (racksCount > currentRows) {
                    for (var i = currentRows + 1; i <= racksCount; i++) {
                        $('#racksCountTable').append(`
                            <tr>
                                <td>${i}</td>
                                <td><input type="text" name="rack[]" value="Rack-${i}" class="form-control form-control-sm" placeholder="Rack Name"></td>
                            </tr>
                        `);
                    }
                }
                // If new count is less, remove rows from the end
                else if (racksCount < currentRows) {
                    var rowsToRemove = currentRows - racksCount;
                    $('#racksCountTable tr').slice(-rowsToRemove).remove();
                }
            }

            // Trigger on input change (works for typing, not just blur)
            $('#racks').on('blur', function(){
                var racks = $(this).val();
                updateRacksTable(racks);
            });

            // Also trigger on change event
            $('#racks').on('change', function(){
                var racks = $(this).val();
                updateRacksTable(racks);
            });
        });
    </script>
@endpush
