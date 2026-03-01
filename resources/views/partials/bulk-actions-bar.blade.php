{{--
    Bulk Actions Bar Partial

    Usage:
    @include('partials.bulk-actions-bar', [
        'itemName' => 'products'
    ])
--}}
<div id="bulkActionsBar" class="d-none align-items-center justify-content-between bg-light border rounded p-2 mb-3">
    <div class="d-flex align-items-center">
        <span id="selectedCount" class="text-muted me-3">0 {{ $itemName ?? 'items' }} selected</span>
        <button type="button" id="clearSelection" class="btn btn-sm btn-outline-secondary me-2">
            <i class="feather-x me-1"></i> Clear
        </button>
    </div>
    <div>
        <button type="button" id="bulkDeleteBtn" class="btn btn-sm btn-danger">
            <i class="feather-trash-2 me-1"></i> Delete Selected
        </button>
    </div>
</div>
