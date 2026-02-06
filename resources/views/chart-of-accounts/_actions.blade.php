<div class="btn-group btn-group-sm">
    @can('chart-of-accounts-view')
        <a href="{{ route('chart-of-accounts.show', $account) }}" class="btn btn-outline-info" title="View">
            <i class="fas fa-eye"></i>
        </a>
    @endcan

    @can('chart-of-accounts-edit')
        <a href="{{ route('chart-of-accounts.edit', $account) }}" class="btn btn-outline-primary" title="Edit">
            <i class="fas fa-edit"></i>
        </a>
    @endcan

    @can('chart-of-accounts-delete')
        @if ($account->canDelete())
            <form action="{{ route('chart-of-accounts.destroy', $account) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this account?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        @endif
    @endcan
</div>