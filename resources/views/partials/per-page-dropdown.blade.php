<div class="d-flex align-items-center gap-2">
    <label class="form-label mb-0 text-muted fs-12">Show:</label>
    <select name="per_page" class="form-select form-select-sm per-page-select" style="width: 75px;">
        @foreach([10, 25, 50, 100] as $option)
            <option value="{{ $option }}" {{ request('per_page', $perPage ?? 25) == $option ? 'selected' : '' }}>{{ $option }}</option>
        @endforeach
    </select>
</div>

@once
@push('scripts')
<script>
$(document).ready(function() {
    $('.per-page-select').on('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', $(this).val());
        url.searchParams.delete('page'); // Reset to first page
        window.location.href = url.toString();
    });
});
</script>
@endpush
@endonce
