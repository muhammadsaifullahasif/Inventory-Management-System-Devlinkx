{{-- Generic Date From / Date To filter fields. Params: $dateFrom, $dateTo --}}
<div class="col-md-2">
    <label class="form-label">Date From</label>
    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? request('date_from') }}">
</div>
<div class="col-md-2">
    <label class="form-label">Date To</label>
    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? request('date_to') }}">
</div>
