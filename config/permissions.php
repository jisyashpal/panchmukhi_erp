<?php

return [

    'modules' => [
        'admin' => 'Administration',
        'ho' => 'Head Office',
        'site' => 'Site Portal',
    ],

    /*
    | Default permissions seeded on install. The "roles" key lists role slugs
    | that receive each permission automatically.
    */
    'defaults' => [
        // Administration
        ['name' => 'Access Admin Portal', 'slug' => 'admin.access', 'module' => 'admin', 'sort_order' => 1, 'roles' => ['super_admin']],
        ['name' => 'Manage Sites / Plants', 'slug' => 'admin.manage_sites', 'module' => 'admin', 'sort_order' => 2, 'roles' => ['super_admin']],
        ['name' => 'Manage Machines', 'slug' => 'admin.manage_machines', 'module' => 'admin', 'sort_order' => 3, 'roles' => ['super_admin']],
        ['name' => 'Manage Suppliers (Master)', 'slug' => 'admin.manage_suppliers', 'module' => 'admin', 'sort_order' => 4, 'roles' => ['super_admin']],
        ['name' => 'Manage Users', 'slug' => 'admin.manage_users', 'module' => 'admin', 'sort_order' => 5, 'roles' => ['super_admin']],
        ['name' => 'Manage Roles', 'slug' => 'admin.manage_roles', 'module' => 'admin', 'sort_order' => 6, 'roles' => ['super_admin']],
        ['name' => 'Manage Permissions', 'slug' => 'admin.manage_permissions', 'module' => 'admin', 'sort_order' => 7, 'roles' => ['super_admin']],
        ['name' => 'Manage Product Catalog', 'slug' => 'admin.manage_products', 'module' => 'admin', 'sort_order' => 8, 'roles' => ['super_admin']],

        // Head Office
        ['name' => 'Access HO Portal', 'slug' => 'ho.access', 'module' => 'ho', 'sort_order' => 1, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'Manage Product Requests', 'slug' => 'ho.manage_product_requests', 'module' => 'ho', 'sort_order' => 2, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'View Purchases & PO', 'slug' => 'ho.view_purchases', 'module' => 'ho', 'sort_order' => 3, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'Manage HO Stocks', 'slug' => 'ho.manage_stocks', 'module' => 'ho', 'sort_order' => 4, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'View Reports', 'slug' => 'ho.view_reports', 'module' => 'ho', 'sort_order' => 5, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'Manage Product Life', 'slug' => 'ho.manage_product_life', 'module' => 'ho', 'sort_order' => 6, 'roles' => ['super_admin', 'ho_data_operator']],
        ['name' => 'Manage Products', 'slug' => 'ho.manage_products', 'module' => 'ho', 'sort_order' => 7, 'roles' => ['super_admin', 'ho_data_operator']],

        // Site Portal
        ['name' => 'Access Site Portal', 'slug' => 'site.access', 'module' => 'site', 'sort_order' => 1, 'roles' => ['super_admin', 'site_in_charge', 'purchaser']],
        ['name' => 'Create Product Requests', 'slug' => 'site.create_product_requests', 'module' => 'site', 'sort_order' => 2, 'roles' => ['super_admin', 'site_in_charge']],
        ['name' => 'View Product Requests', 'slug' => 'site.view_product_requests', 'module' => 'site', 'sort_order' => 3, 'roles' => ['super_admin', 'site_in_charge', 'purchaser']],
        ['name' => 'Manage Purchase Requests', 'slug' => 'site.manage_purchase_requests', 'module' => 'site', 'sort_order' => 4, 'roles' => ['super_admin', 'purchaser']],
        ['name' => 'Manage Site Suppliers', 'slug' => 'site.manage_suppliers', 'module' => 'site', 'sort_order' => 5, 'roles' => ['super_admin', 'purchaser']],
        ['name' => 'Manage Site Stocks', 'slug' => 'site.manage_stocks', 'module' => 'site', 'sort_order' => 6, 'roles' => ['super_admin', 'site_in_charge', 'purchaser']],
        ['name' => 'View Site Purchases', 'slug' => 'site.view_purchases', 'module' => 'site', 'sort_order' => 7, 'roles' => ['super_admin', 'site_in_charge', 'purchaser']],
        ['name' => 'Manage Site Purchases', 'slug' => 'site.manage_purchases', 'module' => 'site', 'sort_order' => 8, 'roles' => ['super_admin', 'purchaser']],
        ['name' => 'Dispatch Orders', 'slug' => 'site.dispatch_orders', 'module' => 'site', 'sort_order' => 9, 'roles' => ['super_admin', 'purchaser']],
    ],

];
