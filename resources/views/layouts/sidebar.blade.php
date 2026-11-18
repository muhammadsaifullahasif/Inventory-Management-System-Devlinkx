<!--! ================================================================ !-->
<!--! [Start] Navigation Menu !-->
<!--! ================================================================ !-->
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand navbar-brand">
                <!-- ========   change your logo/app name in Settings > General   ============ -->
                @php($generalSettings ??= \App\Models\GeneralSetting::current())
                <img src="{{ $generalSettings->logo ? asset($generalSettings->logo) : asset('images/sigma-body-parts-logo.png') }}" alt="" class="logo logo-lg" style="width: 52px;" />
                <img src="{{ $generalSettings->logo ? asset($generalSettings->logo) : asset('images/sigma-body-parts-logo.png') }}" alt="" class="logo logo-sm" style="width: 52px;" />
                {{ $generalSettings->app_name }}
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                <!-- Navigation Caption -->
                <li class="nxl-item nxl-caption">
                    <label>Navigation</label>
                </li>

                <!-- Dashboard -->
                <li class="nxl-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="nxl-link">
                        <span class="nxl-micon"><i class="feather-airplay"></i></span>
                        <span class="nxl-mtext">Dashboard</span>
                    </a>
                </li>

                <!-- Orders -->
                @canany(['view orders', 'add orders', 'edit orders', 'delete orders'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['orders.*']) ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shopping-cart"></i></span>
                            <span class="nxl-mtext">Orders</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view orders', 'edit orders', 'delete orders'])
                            <li class="nxl-item {{ request()->routeIs(['orders.index', 'orders.show', 'orders.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('orders.index') }}">All Orders</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('orders.returns-refunds') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('orders.returns-refunds') }}">Returns &amp; Refunds</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('orders.unmatched-skus') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('orders.unmatched-skus') }}">Unmatched SKUs</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('orders.out-of-stock') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('orders.out-of-stock') }}">Out of Stock Orders</a>
                            </li>
                            @endcan
                            {{-- <li class="nxl-item {{ request()->routeIs('reports.shipping-checklist') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('reports.shipping-checklist') }}">Shipping Checklist</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('reports.out-of-stock') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('reports.out-of-stock') }}">Out of Stock</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('reports.slow-moving-items') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('reports.slow-moving-items') }}">Slow Moving Items</a>
                            </li>
                            <li class="nxl-item {{ request()->routeIs('reports.frequently-ordered-items') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('reports.frequently-ordered-items') }}">Frequently Ordered Items</a>
                            </li> --}}
                        </ul>
                    </li>
                @endcan

                <!-- Messages (buyer conversations from all stores) -->
                @can('view messages')
                    <li class="nxl-item {{ request()->routeIs('messages.*') ? 'active' : '' }}">
                        <a href="{{ route('messages.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-mail"></i></span>
                            <span class="nxl-mtext">Messages</span>
                            <span class="badge bg-danger ms-2 d-none" id="msg-sidebar-badge" title="Conversations with unread messages"></span>
                        </a>
                    </li>
                    <style>
                        /* Collapsed sidebar (icons only): float the counter over the icon's corner.
                           While the collapsed sidebar is hovered it expands, so the normal inline badge returns. */
                        html.minimenu .nxl-navigation:not(:hover) .nxl-link { position: relative; }
                        html.minimenu .nxl-navigation:not(:hover) #msg-sidebar-badge {
                            position: absolute; top: 3px; right: 3px; margin: 0 !important;
                            min-width: 18px; height: 18px; padding: 0 5px;
                            font-size: 10px; line-height: 18px; border-radius: 9px;
                            box-shadow: 0 0 0 2px var(--bs-body-bg, #fff);
                        }
                    </style>
                    @push('scripts')
                    <script>
                        // Unread badge + desktop notifications for new buyer messages.
                        // Polls every 60s. A hidden tab keeps polling only while notifications are switched on
                        // (the toggle lives on the Messages page and is stored in localStorage).
                        (function () {
                            var badge = document.getElementById('msg-sidebar-badge');
                            if (!badge) return;
                            var ON_KEY = 'msgNotifyOn', LAST_KEY = 'msgNotifyLastId';
                            var inboxUrl = @json(route('messages.index'));
                            var countsUrl = @json(route('messages.counts'));

                            function store(key, val) { try { if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, String(val)); } catch (e) {} }
                            function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
                            function notifying() {
                                return 'Notification' in window && Notification.permission === 'granted' && read(ON_KEY) === '1';
                            }

                            function notify(d) {
                                var who = d.latest ? d.latest.buyer : 'a buyer';
                                var title = d.new > 1 ? d.new + ' new buyer messages' : 'New message from ' + who;
                                var body = d.latest ? (d.new > 1 ? 'Latest from ' + who + ': ' : '') + d.latest.preview : '';
                                if (d.latest && d.latest.store) body += (body ? ' · ' : '') + d.latest.store;
                                try {
                                    var n = new Notification(title, {body: body, tag: 'inventory-messages', renotify: true, icon: @json(asset('assets/images/favicon.ico'))});
                                    n.onclick = function () { window.focus(); window.location.href = inboxUrl + (d.new === 1 && d.latest ? '?thread=' + d.latest.thread_id : ''); n.close(); };
                                } catch (e) {}
                            }

                            function load() {
                                var active = notifying();
                                if (document.hidden && !active) return;
                                var last = parseInt(read(LAST_KEY) || '0', 10);
                                var url = countsUrl + (active && last > 0 ? '?after=' + last : '');
                                fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'})
                                    .then(function (r) { return r.ok ? r.json() : null; })
                                    .then(function (d) {
                                        if (!d) return;
                                        badge.textContent = d.unread > 99 ? '99+' : d.unread;
                                        badge.classList.toggle('d-none', !d.unread);

                                        if (!notifying()) return;
                                        // Re-read: another open tab may already have announced these messages.
                                        var stored = parseInt(read(LAST_KEY) || '0', 10);
                                        if (stored === 0) { store(LAST_KEY, d.latest_id); return; }   // first run: baseline, no alert
                                        if (d.latest_id > stored) {
                                            store(LAST_KEY, d.latest_id);
                                            // No alert when the person is already looking at the inbox.
                                            var watching = !document.hidden && document.hasFocus() && /\/messages/.test(location.pathname);
                                            if (d.new > 0 && stored === last && !watching) notify(d);
                                        }
                                    })
                                    .catch(function () {});
                            }
                            load();
                            setInterval(load, 60000);
                            window.addEventListener('messages:counts-changed', load);
                        })();
                    </script>
                    @endpush
                @endcan

                <!-- Products -->
                @canany(['view products', 'add products', 'edit products', 'delete products', 'view categories', 'add categories', 'edit categories', 'delete categories', 'view brands', 'add brands', 'edit brands', 'delete brands', 'view market-research', 'export market-research'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['products.*', 'categories.*', 'brands.*', 'market-research.*']) ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-package"></i></span>
                            <span class="nxl-mtext">Products</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view products', 'edit products', 'delete products'])
                            <li class="nxl-item {{ request()->routeIs(['products.index', 'products.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('products.index') }}">All Products</a>
                            </li>
                            @endcan
                            @can('view market-research')
                            <li class="nxl-item {{ request()->routeIs('market-research.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('market-research.index') }}">Market Research</a>
                            </li>
                            @endcan
                            @can('add products')
                            <li class="nxl-item {{ request()->routeIs('products.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('products.create') }}">Add Product</a>
                            </li>
                            @endcan
                            @canany(['view categories', 'add categories', 'edit categories', 'delete categories'])
                            <li class="nxl-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('categories.index') }}">Categories</a>
                            </li>
                            @endcan
                            @canany(['view brands', 'add brands', 'edit brands', 'delete brands'])
                            <li class="nxl-item {{ request()->routeIs('brands.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('brands.index') }}">Brands</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Purchases -->
                @canany(['view purchases', 'add purchases', 'edit purchases', 'delete purchases'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs('purchases.*') ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shopping-bag"></i></span>
                            <span class="nxl-mtext">Purchases</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view purchases', 'edit purchases', 'delete purchases'])
                            <li class="nxl-item {{ request()->routeIs(['purchases.index', 'purchases.show', 'purchases.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('purchases.index') }}">All Purchases</a>
                            </li>
                            @endcan
                            @can('add purchases')
                            <li class="nxl-item {{ request()->routeIs('purchases.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('purchases.create') }}">Add Purchase</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Warehouses -->
                @canany(['view warehouses', 'add warehouses', 'edit warehouses', 'delete warehouses', 'view racks', 'add racks', 'edit racks', 'delete racks'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['warehouses.*', 'racks.*']) ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-home"></i></span>
                            <span class="nxl-mtext">Warehouses</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view warehouses', 'edit warehouses', 'delete warehouses'])
                            <li class="nxl-item {{ request()->routeIs(['warehouses.index', 'warehouses.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('warehouses.index') }}">All Warehouses</a>
                            </li>
                            @endcan
                            @can('add warehouses')
                            <li class="nxl-item {{ request()->routeIs('warehouses.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('warehouses.create') }}">Add Warehouse</a>
                            </li>
                            @endcan
                            @canany(['view racks', 'add racks', 'edit racks', 'delete racks'])
                            <li class="nxl-item {{ request()->routeIs('racks.*') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('racks.index') }}">Racks</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Sales Channels -->
                @canany(['view sales-channels', 'add sales-channels', 'edit sales-channels', 'delete sales-channels'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs('sales-channels.*') ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-share-2"></i></span>
                            <span class="nxl-mtext">Sales Channels</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view sales-channels', 'edit sales-channels', 'delete sales-channels'])
                            <li class="nxl-item {{ request()->routeIs(['sales-channels.index', 'sales-channels.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('sales-channels.index') }}">All Channels</a>
                            </li>
                            @endcan
                            @can('add sales-channels')
                            <li class="nxl-item {{ request()->routeIs('sales-channels.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('sales-channels.create') }}">Add Channel</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Suppliers -->
                @canany(['view suppliers', 'add suppliers', 'edit suppliers', 'delete suppliers'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs('suppliers.*') ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-truck"></i></span>
                            <span class="nxl-mtext">Suppliers</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view suppliers', 'edit suppliers', 'delete suppliers'])
                            <li class="nxl-item {{ request()->routeIs(['suppliers.index', 'suppliers.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('suppliers.index') }}">All Suppliers</a>
                            </li>
                            @endcan
                            @can('add suppliers')
                            <li class="nxl-item {{ request()->routeIs('suppliers.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('suppliers.create') }}">Add Supplier</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Shipping -->
                @canany(['view shipping', 'add shipping', 'edit shipping', 'delete shipping'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs('shipping.*') ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Shipping</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['view shipping', 'edit shipping', 'delete shipping'])
                            <li class="nxl-item {{ request()->routeIs(['shipping.index', 'shipping.edit']) ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('shipping.index') }}">All Shipping</a>
                            </li>
                            @endcan
                            @can('add shipping')
                            <li class="nxl-item {{ request()->routeIs('shipping.create') ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('shipping.create') }}">Add Shipping</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['chart-of-accounts-view', 'chart-of-accounts-add', 'chart-of-accounts-edit', 'chart-of-accounts-delete', 'bills-view', 'bills-add', 'bills-edit', 'bills-delete', 'bills-post', 'payments-view', 'payments-add', 'payments-delete', 'journal-entries-view', 'general-ledger-view', 'accounting-reports-view', 'accounting-reports-export'])
                    <!-- Accounting Section Caption -->
                    <li class="nxl-item nxl-caption">
                        <label>Accounting</label>
                    </li>

                    <!-- Accounting -->
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['chart-of-accounts.*', 'bills.*', 'payments.*', 'journal-entries.*', 'general-ledger.*', 'reports.*']) ? 'active nxl-trigger' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-dollar-sign"></i></span>
                            <span class="nxl-mtext">Accounting</span>
                            <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @canany(['chart-of-accounts-view', 'chart-of-accounts-add', 'chart-of-accounts-edit', 'chart-of-accounts-delete'])
                                <li class="nxl-item {{ request()->routeIs('chart-of-accounts.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('chart-of-accounts.index') }}">Chart of Accounts</a>
                                </li>
                            @endcanany
                            @canany(['bills-view', 'bills-add', 'bills-edit', 'bills-delete', 'bills-post'])
                                <li class="nxl-item {{ request()->routeIs('bills.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('bills.index') }}">Bills</a>
                                </li>
                            @endcanany
                            @canany(['payments-view', 'payments-add', 'payments-delete'])
                                <li class="nxl-item {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('payments.index') }}">Payments</a>
                                </li>
                            @endcanany
                            @can('journal-entries-view')
                                <li class="nxl-item {{ request()->routeIs('journal-entries.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('journal-entries.index') }}">Journal Entries</a>
                                </li>
                            @endcan
                            @can('general-ledger-view')
                                <li class="nxl-item {{ request()->routeIs('general-ledger.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('general-ledger.index') }}">General Ledger</a>
                                </li>
                            @endcan
                            @can('accounting-reports-view')
                                <li class="nxl-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('reports.index') }}">Reports</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                @canany(['view users', 'add users', 'edit users', 'delete users', 'view roles', 'add roles', 'edit roles', 'delete roles', 'view permissions', 'add permissions', 'edit permissions', 'delete permissions'])
                    <!-- User Management Section Caption -->
                    <li class="nxl-item nxl-caption">
                        <label>User Management</label>
                    </li>

                    <!-- Users -->
                    @canany(['view users', 'add users', 'edit users', 'delete users'])
                        <li class="nxl-item nxl-hasmenu {{ request()->routeIs('users.*') ? 'active nxl-trigger' : '' }}">
                            <a href="javascript:void(0);" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-users"></i></span>
                                <span class="nxl-mtext">Users</span>
                                <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                @canany(['view users', 'edit users', 'delete users'])
                                    <li class="nxl-item {{ request()->routeIs(['users.index', 'users.edit']) ? 'active' : '' }}">
                                        <a class="nxl-link" href="{{ route('users.index') }}">All Users</a>
                                    </li>
                                @endcan
                                @can('add users')
                                    <li class="nxl-item {{ request()->routeIs('users.create') ? 'active' : '' }}">
                                        <a class="nxl-link" href="{{ route('users.create') }}">Add User</a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                    @endcan

                    <!-- Roles & Permissions -->
                    @canany(['view roles', 'add roles', 'edit roles', 'delete roles', 'view permissions', 'add permissions', 'edit permissions', 'delete permissions'])
                        <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['roles.*', 'permissions.*']) ? 'active nxl-trigger' : '' }}">
                            <a href="javascript:void(0);" class="nxl-link">
                                <span class="nxl-micon"><i class="feather-shield"></i></span>
                                <span class="nxl-mtext">Roles & Permissions</span>
                                <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                            </a>
                            <ul class="nxl-submenu">
                                @canany(['view roles', 'edit roles', 'delete roles'])
                                    <li class="nxl-item {{ request()->routeIs(['roles.index', 'roles.edit']) ? 'active' : '' }}">
                                        <a class="nxl-link" href="{{ route('roles.index') }}">All Roles</a>
                                    </li>
                                @endcan
                                @can('add roles')
                                    <li class="nxl-item {{ request()->routeIs('roles.create') ? 'active' : '' }}">
                                        <a class="nxl-link" href="{{ route('roles.create') }}">Add Role</a>
                                    </li>
                                @endcan
                                @canany(['view permissions', 'add permissions', 'edit permissions', 'delete permissions'])
                                    <li class="nxl-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                                        <a class="nxl-link" href="{{ route('permissions.index') }}">Permissions</a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                    @endcan
                @endcan

                @canany(['view system-health', 'manage system-health', 'view backups', 'create backups', 'delete backups', 'manage backup-settings', 'view audit-logs', 'manage audit-settings'])
                    <!-- System Section Caption -->
                    <li class="nxl-item nxl-caption">
                        <label>System</label>
                    </li>

                    @can('view system-health')
                    <!-- System Health -->
                    <li class="nxl-item {{ request()->routeIs('system-health.*') ? 'active' : '' }}">
                        <a class="nxl-link" href="{{ route('system-health.index') }}">
                            <span class="nxl-micon"><i class="feather-activity"></i></span>
                            <span class="nxl-mtext">System Health</span>
                        </a>
                    </li>
                    @endcan

                    @canany(['view backups', 'manage backup-settings'])
                    <!-- Backups -->
                    <li class="nxl-item {{ request()->routeIs('backups.*') ? 'active' : '' }}">
                        <a class="nxl-link" href="{{ route('backups.index') }}">
                            <span class="nxl-micon"><i class="feather-archive"></i></span>
                            <span class="nxl-mtext">Backups</span>
                        </a>
                    </li>
                    @endcan

                    @canany(['view audit-logs', 'manage audit-settings'])
                    <!-- Audit Trail -->
                    <li class="nxl-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                        <a class="nxl-link" href="{{ route('audit-logs.index') }}">
                            <span class="nxl-micon"><i class="feather-shield"></i></span>
                            <span class="nxl-mtext">Audit Trail</span>
                        </a>
                    </li>
                    @endcanany
                @endcanany

                @canany(['manage general-settings', 'manage audit-settings', 'manage backup-settings', 'edit shipping', 'manage message-settings'])
                    <!-- Settings Section Caption -->
                    <li class="nxl-item nxl-caption">
                        <label>Settings</label>
                    </li>

                    <!-- Settings -->
                    <li class="nxl-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <a class="nxl-link" href="{{ route('settings.index') }}">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span>
                        </a>
                    </li>
                @endcanany
            </ul>

            <!-- Sidebar Info Card -->
            @canany(['view orders', 'add orders', 'edit orders', 'delete orders', 'view products', 'add products', 'edit products', 'delete products'])
                <div class="card text-center">
                    <div class="card-body">
                        <i class="feather-box fs-4 text-dark"></i>
                        <h6 class="mt-4 text-dark fw-bolder">Inventory System</h6>
                        <p class="fs-11 my-3 text-muted">Manage your products, orders, and warehouse efficiently.</p>
                        <div class="d-flex gap-2">
                            @canany(['view products', 'add products', 'edit products', 'delete products'])
                                <a href="{{ route('products.index') }}" class="btn btn-sm btn-light-brand flex-fill">Products</a>
                            @endcan
                            @canany(['view orders', 'add orders', 'edit orders', 'delete orders'])
                                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-primary flex-fill">Orders</a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</nav>
<!--! ================================================================ !-->
<!--! [End] Navigation Menu !-->
<!--! ================================================================ !-->
