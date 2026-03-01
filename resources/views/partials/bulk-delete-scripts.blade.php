{{--
    Bulk Delete Scripts Partial

    Usage:
    @include('partials.bulk-delete-scripts', [
        'routeName' => 'products.bulk-delete',
        'itemName' => 'products'
    ])
--}}
<script>
    $(document).ready(function() {
        var selectedIds = [];
        var itemName = '{{ $itemName ?? "items" }}';
        var bulkDeleteUrl = '{{ route($routeName ?? "bulk-delete") }}';
        var csrfToken = '{{ csrf_token() }}';

        // Select All checkbox
        $(document).on('change', '#selectAll', function() {
            var isChecked = $(this).prop('checked');
            $('.row-checkbox').prop('checked', isChecked);
            updateSelectedIds();
            updateBulkActionsBar();
        });

        // Individual row checkbox
        $(document).on('change', '.row-checkbox', function() {
            updateSelectedIds();
            updateSelectAllState();
            updateBulkActionsBar();
        });

        // Update selected IDs array
        function updateSelectedIds() {
            selectedIds = [];
            $('.row-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });
        }

        // Update Select All checkbox state
        function updateSelectAllState() {
            var totalCheckboxes = $('.row-checkbox').length;
            var checkedCheckboxes = $('.row-checkbox:checked').length;

            if (checkedCheckboxes === 0) {
                $('#selectAll').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#selectAll').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#selectAll').prop('checked', false).prop('indeterminate', true);
            }
        }

        // Update bulk actions bar visibility
        function updateBulkActionsBar() {
            var count = selectedIds.length;
            if (count > 0) {
                $('#bulkActionsBar').removeClass('d-none').addClass('d-flex');
                $('#selectedCount').text(count + ' ' + itemName + ' selected');
            } else {
                $('#bulkActionsBar').removeClass('d-flex').addClass('d-none');
            }
        }

        // Clear selection
        $(document).on('click', '#clearSelection', function() {
            $('#selectAll').prop('checked', false).prop('indeterminate', false);
            $('.row-checkbox').prop('checked', false);
            selectedIds = [];
            updateBulkActionsBar();
        });

        // Bulk delete button
        $(document).on('click', '#bulkDeleteBtn', function() {
            if (selectedIds.length === 0) {
                alert('Please select at least one item to delete.');
                return;
            }

            var confirmMsg = 'Are you sure you want to delete ' + selectedIds.length + ' ' + itemName + '?\n\nThis action cannot be undone.';

            if (confirm(confirmMsg)) {
                var $btn = $(this);
                $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Deleting...');

                $.ajax({
                    url: bulkDeleteUrl,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message and reload
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to delete ' + itemName);
                            $btn.prop('disabled', false).html('<i class="feather-trash-2 me-1"></i> Delete Selected');
                        }
                    },
                    error: function(xhr) {
                        var message = xhr.responseJSON?.message || 'An error occurred while deleting ' + itemName;
                        alert(message);
                        $btn.prop('disabled', false).html('<i class="feather-trash-2 me-1"></i> Delete Selected');
                    }
                });
            }
        });
    });
</script>
