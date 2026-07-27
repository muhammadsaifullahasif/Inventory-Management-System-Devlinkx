@extends('layouts.app')

@section('header')
    <!-- [ page-header ] start -->
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Edit Warehouse</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Warehouses</a></li>
                <li class="breadcrumb-item">Edit Warehouse</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="{{ route('warehouses.index') }}" class="btn btn-light-brand">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back to Warehouses</span>
                    </a>
                    @can('add warehouses')
                        <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
                            <i class="feather-plus me-2"></i>
                            <span>Add Warehouse</span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    <!-- [ page-header ] end -->
@endsection

@section('content')
<div class="col-12">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('warehouses.update', $warehouse->id) }}" method="post">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $warehouse->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="Warehouse Name" required>
                    @error('name')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" id="is_default" value="1" name="is_default" class="form-check-input" {{ old('is_default', $warehouse->is_default) == 1 ? 'checked' : '' }}>
                        <label for="is_default" class="form-check-label">Is Default</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="racks" class="form-label">No of Racks</label>
                    <input type="number" id="racks" name="racks" value="{{ old('racks', $warehouse->racks->count()) }}" class="form-control" placeholder="Number of Racks in Warehouse" min="0">
                    @error('racks')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div id="racksCountContainer" class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;">#</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody id="racksCountTable">
                            @foreach ($warehouse->racks as $index => $rack)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <input type="hidden" name="rack_id[]" value="{{ $rack->id }}">
                                        <input type="text" name="rack[]" value="{{ old('rack.'.$index, $rack->name) }}" class="form-control form-control-sm" placeholder="Rack Name">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-2"></i>Update Warehouse
                    </button>
                    <a href="{{ route('warehouses.index') }}" class="btn btn-light-brand">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function(){
            function updateRacksTable(count) {
                var currentRows = $('#racksCountTable tr').length;
                var racksCount = parseInt(count) || 0;

                if (racksCount < 0) racksCount = 0;

                if (racksCount > currentRows) {
                    for (var i = currentRows + 1; i <= racksCount; i++) {
                        $('#racksCountTable').append(`
                            <tr>
                                <td>${i}</td>
                                <td>
                                    <input type="hidden" name="rack_id[]" value="">
                                    <input type="text" name="rack[]" value="Rack-${i}" class="form-control form-control-sm" placeholder="Rack Name">
                                </td>
                            </tr>
                        `);
                    }
                } else if (racksCount < currentRows) {
                    var rowsToRemove = currentRows - racksCount;
                    $('#racksCountTable tr').slice(-rowsToRemove).remove();
                }
            }

            $('#racks').on('blur change', function(){
                updateRacksTable($(this).val());
            });
        });
    </script>
@endpush
