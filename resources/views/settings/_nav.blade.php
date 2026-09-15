<div class="col-12">
    <ul class="nav nav-pills mb-4">
        @can('manage general-settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.general.*') ? 'active' : '' }}" href="{{ route('settings.general.edit') }}">
                    <i class="feather-sliders me-1"></i> General
                </a>
            </li>
        @endcan
        @can('manage audit-settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.audit-log.*') ? 'active' : '' }}" href="{{ route('settings.audit-log.edit') }}">
                    <i class="feather-archive me-1"></i> Audit Log
                </a>
            </li>
        @endcan
        @can('manage backup-settings')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.backup.*') ? 'active' : '' }}" href="{{ route('settings.backup.edit') }}">
                    <i class="feather-hard-drive me-1"></i> Backup
                </a>
            </li>
        @endcan
        @can('edit shipping')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.shipping.*') ? 'active' : '' }}" href="{{ route('settings.shipping.edit') }}">
                    <i class="feather-send me-1"></i> Shipping
                </a>
            </li>
        @endcan
    </ul>
</div>
