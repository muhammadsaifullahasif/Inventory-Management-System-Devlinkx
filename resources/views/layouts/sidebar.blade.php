<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-light-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index3.html" class="brand-link">
        <img src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="AdminLTE Logo" class="brand-image img-circle elevation-3"
            style="opacity: .8">
        <span class="brand-text font-weight-light">AdminLTE 3</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block">Alexander Pierce</a>
            </div>
        </div>

        <!-- SidebarSearch Form -->
        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="Search"
                    aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                data-accordion="false">
                <!-- Add icons to the links using the .nav-icon class
                    with font-awesome or any other icon font library -->
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                @canany(['view orders', 'add orders', 'edit orders', 'delete orders'])
                    <li class="nav-item {{ request()->routeIs(['orders.*']) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(['orders.*']) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-th"></i>
                            <p>
                                Orders
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view orders', 'edit orders', 'delete orders'])
                                <li class="nav-item">
                                    <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Orders</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['view products', 'add products', 'edit products', 'delete products', 'view categories', 'add categories', 'edit categories', 'delete categories', 'view brands', 'add brands', 'edit brands', 'delete brands'])
                    <li class="nav-item {{ request()->routeIs(['products.*', 'categories.*', 'brands.*']) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(['products.*', 'categories.*', 'brands.*']) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-th"></i>
                            <p>
                                Products
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view products', 'edit products', 'delete products'])
                                <li class="nav-item">
                                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Products</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add products')
                                <li class="nav-item">
                                    <a href="{{ route('products.create') }}" class="nav-link {{ request()->routeIs('products.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Product</p>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view categories', 'add categories', 'edit categories', 'delete categories'])
                                <li class="nav-item">
                                    <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Categories</p>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view brands', 'add brands', 'edit brands', 'delete brands'])
                                <li class="nav-item">
                                    <a href="{{ route('brands.index') }}" class="nav-link {{ request()->routeIs('brands.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Brands</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['view purchases', 'add purchases', 'edit purchases', 'delete purchases'])
                    <li class="nav-item {{ request()->routeIs('purchases.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-th"></i>
                            <p>
                                Purchases
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view purchases', 'edit purchases', 'delete purchases'])
                                <li class="nav-item">
                                    <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Purchases</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add purchases')
                                <li class="nav-item">
                                    <a href="{{ route('purchases.create') }}" class="nav-link {{ request()->routeIs('purchases.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Purchase</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['view warehouses', 'add warehouses', 'edit warehouses', 'delete warehouses', 'view racks', 'add racks', 'edit racks', 'delete racks'])
                    <li class="nav-item {{ request()->routeIs(['warehouses.*', 'racks.*']) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(['warehouses.*', 'racks.*']) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-warehouse"></i>
                            <p>
                                Warehouses
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view warehouses', 'edit warehouses', 'delete warehouses'])
                                <li class="nav-item">
                                    <a href="{{ route('warehouses.index') }}" class="nav-link {{ request()->routeIs('warehouses.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Warehouses</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add warehouses')
                                <li class="nav-item">
                                    <a href="{{ route('warehouses.create') }}" class="nav-link {{ request()->routeIs('warehouses.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Warehouse</p>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view racks', 'add racks', 'edit racks', 'delete racks'])
                                <li class="nav-item">
                                    <a href="{{ route('racks.index') }}" class="nav-link {{ request()->routeIs('racks.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Racks</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['view sales-channels', 'add sales-channels', 'edit sales-channels', 'delete sales-channels'])
                    <li class="nav-item {{ request()->routeIs('sales-channels.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('sales-channels.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-th"></i>
                            <p>
                                Sale Channels
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view sales-channels', 'edit sales-channels', 'delete sales-channels'])
                                <li class="nav-item">
                                    <a href="{{ route('sales-channels.index') }}" class="nav-link {{ request()->routeIs('sales-channels.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Sales Channel</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add sales-channel')
                                <li class="nav-item">
                                    <a href="{{ route('sales-channels.create') }}" class="nav-link {{ request()->routeIs('sales-channels.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Sales Channel</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['view suppliers', 'add suppliers', 'edit suppliers', 'delete suppliers'])
                    <li class="nav-item {{ request()->routeIs('suppliers.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-ninja"></i>
                            <p>
                                Suppliers
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view suppliers', 'edit suppliers', 'delete suppliers'])
                                <li class="nav-item">
                                    <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Suppliers</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add suppliers')
                                <li class="nav-item">
                                    <a href="{{ route('suppliers.create') }}" class="nav-link {{ request()->routeIs('suppliers.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Supplier</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                <!-- Accounting Section -->
                {{-- @canany(['']) --}}
                {{-- @endcan --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('chart-of-accounts.*') || request()->routeIs('bills.*') || request()->routeIs('payments.*') ? '' : 'collapsed' }}" 
                    data-bs-toggle="collapse" 
                    href="#accountingMenu" 
                    role="button">
                        <i class="bi bi-calculator"></i>
                        <span>Accounting</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse {{ request()->routeIs('chart-of-accounts.*') || request()->routeIs('bills.*') || request()->routeIs('payments.*') ? 'show' : '' }}" id="accountingMenu">
                        <ul class="nav flex-column ms-3">
                            @can('chart-of-accounts-view')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('chart-of-accounts.*') ? 'active' : '' }}" 
                                href="{{ route('chart-of-accounts.index') }}">
                                    <i class="bi bi-diagram-3"></i> Chart of Accounts
                                </a>
                            </li>
                            @endcan

                            @can('bills-view')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('bills.*') ? 'active' : '' }}" 
                                href="#">
                                    <i class="bi bi-receipt"></i> Bills
                                </a>
                            </li>
                            @endcan

                            @can('payments-view')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}" 
                                href="#">
                                    <i class="bi bi-cash-stack"></i> Payments
                                </a>
                            </li>
                            @endcan

                            @can('journal-entries-view')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('journal-entries.*') ? 'active' : '' }}" 
                                href="#">
                                    <i class="bi bi-journal-text"></i> Journal Entries
                                </a>
                            </li>
                            @endcan

                            @can('accounting-reports-view')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" 
                                href="#">
                                    <i class="bi bi-bar-chart"></i> Reports
                                </a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>

                @canany(['view users', 'add users', 'edit users', 'delete users'])
                    <li class="nav-item {{ request()->routeIs('users.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                Users
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view users', 'edit users', 'delete users'])
                                <li class="nav-item">
                                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Users</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add users')
                                <li class="nav-item">
                                    <a href="{{ route('users.create') }}" class="nav-link {{ request()->routeIs('users.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add User</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan
                
                @canany(['view roles', 'add roles', 'edit roles', 'delete roles', 'view permissions', 'add permissions', 'edit permissions', 'delete permissions'])
                    <li class="nav-item {{ request()->routeIs(['roles.*', 'permissions.*']) ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs(['roles.*', 'permissions.*']) ? 'active' : '' }}">
                            <i class="nav-icon fas fa-user-tag"></i>
                            <p>
                                Roles
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            @canany(['view roles', 'edit roles', 'delete roles'])
                                <li class="nav-item">
                                    <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.index') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>All Roles</p>
                                    </a>
                                </li>
                            @endcan
                            @can('add roles')
                                <li class="nav-item">
                                    <a href="{{ route('roles.create') }}" class="nav-link {{ request()->routeIs('roles.create') ? 'active' : '' }}">
                                        <i class="fas fa-plus-circle nav-icon"></i>
                                        <p>Add Role</p>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view permissions', 'add permissions', 'edit permissions', 'delete permissions'])
                                <li class="nav-item">
                                    <a href="{{ route('permissions.index') }}" class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Permissions</p>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
