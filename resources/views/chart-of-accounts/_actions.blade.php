<div class="hstack gap-2 justify-content-end">
    @can('chart-of-accounts-view')
        <a href="{{ route('chart-of-accounts.show', $account) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View">
            <i class="feather-eye"></i>
        </a>
    @endcan

    @can('chart-of-accounts-edit')
        <a href="{{ route('chart-of-accounts.edit', $account) }}" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Edit">
            <i class="feather-edit-3"></i>
        </a>
    @endcan

    @can('chart-of-accounts-delete')
        @if ($account->canDelete())
            <form action="{{ route('chart-of-accounts.destroy', $account) }}" method="POST" id="account-{{ $account->id }}-delete-form">
                @csrf
                @method('DELETE')
            </form>
            <a href="javascript:void(0)" data-id="{{ $account->id }}" class="avatar-text avatar-md text-danger delete-btn" data-bs-toggle="tooltip" title="Delete">
                <i class="feather-trash-2"></i>
            </a>
        @endif
    @endcan
</div>

@push('scripts')
<script>
    $(document).ready(function(){
        $(document).on('click', '.delete-btn', function(e){
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this account?')) {
                $('#account-' + id + '-delete-form').submit();
            } else {
                e.preventDefault();
                return false;
            }
        });
    });
</script>
@endpush
