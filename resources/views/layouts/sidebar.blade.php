<!--! ================================================================ !-->
<!--! [Start] Navigation Menu !-->
<!--! ================================================================ !-->
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand navbar-brand">
                <!-- ========   change your logo here   ============ -->
                <img src="{{ asset('images/sigma-body-parts-logo.png') }}" alt="" class="logo logo-lg" style="width: 52px;" />
                <img src="{{ asset('images/sigma-body-parts-logo.png') }}" alt="" class="logo logo-sm" style="width: 52px;" />
                Sigma Body Parts
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
                    <li class="nxl-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                        <a href="{{ route('orders.index') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-shopping-cart"></i></span>
                            <span class="nxl-mtext">Orders</span>
                        </a>
                    </li>
                @endcan

                <!-- Products -->
                @canany(['view products', 'add products', 'edit products', 'delete products', 'view categories', 'add categories', 'edit categories', 'delete categories', 'view brands', 'add brands', 'edit brands', 'delete brands'])
                    <li class="nxl-item nxl-hasmenu {{ request()->routeIs(['products.*', 'categories.*', 'brands.*']) ? 'active nxl-trigger' : '' }}">
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
                            @can('add sales-channel')
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

                @canany(['chart-of-accounts-view', 'chart-of-accounts-add', 'chart-of-accounts-edit', 'chart-of-accounts-delete', 'bills-view', 'bills-add', 'bills-edit', 'bills-delete', 'bills-post', 'payments-view', 'payments-add', 'payments-delete', 'journal-entries-view', 'geenral-ledger-view', 'accounting-reports-view', 'accounting-reports-export'])
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
                            @endcan
                            @canany(['bills-view', 'bills-add', 'bills-edit', 'bill-delete', 'bill-post'])
                                <li class="nxl-item {{ request()->routeIs('bills.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('bills.index') }}">Bills</a>
                                </li>
                            @endcan
                            @canany(['payments-view', 'payments-add', 'payments-delete'])
                                <li class="nxl-item {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                                    <a class="nxl-link" href="{{ route('payments.index') }}">Payments</a>
                                </li>
                            @endcan
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
                @endcan

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
