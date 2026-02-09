<div class="btn-group btn-group-sm">
    @can('chart-of-accounts-view')
        <a href="{{ route('chart-of-accounts.show', $account) }}" class="btn btn-outline-info btn-sm" title="View">
            <i class="fas fa-eye"></i>
        </a>
    @endcan

    @can('chart-of-accounts-edit')
        <a href="{{ route('chart-of-accounts.edit', $account) }}" class="btn btn-outline-primary btn-sm" title="Edit">
            <i class="fas fa-edit"></i>
        </a>
    @endcan

    @can('chart-of-accounts-delete')
        @if ($account->canDelete())
            <form action="{{ route('chart-of-accounts.destroy', $account) }}" method="POST" class="d-inline btn btn-outline-danger btn-sm" onsubmit="return confirm('Are you sure you want to delete this account?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-transparent border-0 text-white p-0" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        @endif
    @endcan
</div>