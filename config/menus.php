<?php

use App\Support\PermissionSlug;

return [

    'site' => [
        'items' => [
            [
                'type' => 'link',
                'label' => 'Dashboard',
                'route' => 'site.dashboard',
                'icon' => 'bi-speedometer2',
                'permission' => PermissionSlug::SITE_ACCESS,
                'routes' => ['site.dashboard'],
            ],
            [
                'type' => 'group',
                'label' => 'Request & PO',
                'icon' => 'bi-clipboard-check',
                'permission' => PermissionSlug::SITE_VIEW_PRODUCT_REQUESTS,
                'routes' => ['site.product-requests.*', 'site.purchase-requests.*', 'site.purchases.*', 'site.suppliers.*', 'site.dispatch.*'],
                'any_permission' => [
                    PermissionSlug::SITE_VIEW_PRODUCT_REQUESTS,
                    PermissionSlug::SITE_CREATE_PRODUCT_REQUESTS,
                    PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS,
                    PermissionSlug::SITE_VIEW_PURCHASES,
                    PermissionSlug::SITE_MANAGE_PURCHASES,
                    PermissionSlug::SITE_MANAGE_SUPPLIERS,
                    PermissionSlug::SITE_DISPATCH_ORDERS,
                ],
                'children' => [
                    [
                        'label' => 'Create Request',
                        'route' => 'site.product-requests.create',
                        'icon' => 'bi-plus-circle',
                        'permission' => PermissionSlug::SITE_CREATE_PRODUCT_REQUESTS,
                        'routes' => ['site.product-requests.create'],
                    ],
                    [
                        'label' => 'Product Requests',
                        'route' => 'site.product-requests.index',
                        'icon' => 'bi-list-ul',
                        'permission' => PermissionSlug::SITE_VIEW_PRODUCT_REQUESTS,
                        'routes' => ['site.product-requests.index', 'site.product-requests.show', 'site.product-requests.edit'],
                    ],
                    [
                        'label' => 'Purchase Requests',
                        'route' => 'site.purchase-requests.index',
                        'icon' => 'bi-cart-check',
                        'permission' => PermissionSlug::SITE_MANAGE_PURCHASE_REQUESTS,
                        'routes' => ['site.purchase-requests.*'],
                    ],
                    [
                        'label' => 'Create PO',
                        'route' => 'site.purchases.create',
                        'icon' => 'bi-plus-circle',
                        'permission' => PermissionSlug::SITE_MANAGE_PURCHASES,
                        'routes' => ['site.purchases.create'],
                    ],
                    [
                        'label' => 'Purchases',
                        'route' => 'site.purchases.index',
                        'icon' => 'bi-receipt',
                        'permission' => PermissionSlug::SITE_VIEW_PURCHASES,
                        'routes' => ['site.purchases.index', 'site.purchases.show'],
                        'hide_if' => PermissionSlug::SITE_DISPATCH_ORDERS,
                    ],
                    [
                        'label' => 'Purchase Orders',
                        'route' => 'site.purchases.index',
                        'icon' => 'bi-receipt',
                        'permission' => PermissionSlug::SITE_DISPATCH_ORDERS,
                        'routes' => ['site.purchases.*'],
                    ],
                    [
                        'label' => 'Order Dispatch',
                        'route' => 'site.dispatch.index',
                        'icon' => 'bi-send',
                        'permission' => PermissionSlug::SITE_DISPATCH_ORDERS,
                        'routes' => ['site.dispatch.*'],
                    ],
                    [
                        'label' => 'Suppliers',
                        'route' => 'site.suppliers.index',
                        'icon' => 'bi-building',
                        'permission' => PermissionSlug::SITE_MANAGE_SUPPLIERS,
                        'routes' => ['site.suppliers.*'],
                    ],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Inventory',
                'icon' => 'bi-boxes',
                'permission' => PermissionSlug::SITE_MANAGE_STOCKS,
                'routes' => ['site.stocks.*'],
                'children' => [
                    [
                        'label' => 'Stocks',
                        'route' => 'site.stocks.index',
                        'icon' => 'bi-boxes',
                        'permission' => PermissionSlug::SITE_MANAGE_STOCKS,
                        'routes' => ['site.stocks.*'],
                    ],
                ],
            ],
            [
                'type' => 'link',
                'label' => 'Notifications',
                'route' => 'site.notifications.index',
                'icon' => 'bi-bell',
                'permission' => PermissionSlug::SITE_ACCESS,
                'routes' => ['site.notifications.*'],
            ],
        ],
    ],

    'ho' => [
        'label' => 'HO Portal',
        'items' => [
            [
                'type' => 'link',
                'label' => 'Dashboard',
                'route' => 'ho.dashboard',
                'icon' => 'bi-speedometer2',
                'permission' => PermissionSlug::HO_ACCESS,
                'routes' => ['ho.dashboard'],
            ],
            [
                'type' => 'group',
                'label' => 'Request & PO',
                'icon' => 'bi-clipboard-check',
                'permission' => PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS,
                'routes' => ['ho.product-requests.*', 'ho.purchase-list.*', 'ho.purchases.*'],
                'any_permission' => [
                    PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS,
                    PermissionSlug::HO_VIEW_PURCHASES,
                ],
                'children' => [
                    ['label' => 'Product Requests', 'route' => 'ho.product-requests.index', 'icon' => 'bi-clipboard-check', 'permission' => PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS, 'routes' => ['ho.product-requests.*']],
                    ['label' => 'Purchase List', 'route' => 'ho.purchase-list.index', 'icon' => 'bi-receipt', 'permission' => PermissionSlug::HO_VIEW_PURCHASES, 'routes' => ['ho.purchase-list.*']],
                    ['label' => 'Purchases', 'route' => 'ho.purchases.index', 'icon' => 'bi-cart-check', 'permission' => PermissionSlug::HO_VIEW_PURCHASES, 'routes' => ['ho.purchases.index', 'ho.purchases.show']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Product',
                'icon' => 'bi-box-seam',
                'permission' => PermissionSlug::HO_MANAGE_PRODUCTS,
                'routes' => ['ho.categories.*', 'ho.sub-categories.*', 'ho.brands.*', 'ho.units.*', 'ho.products.*'],
                'children' => [
                    ['label' => 'Categories', 'route' => 'ho.categories.index', 'icon' => 'bi-folder', 'permission' => PermissionSlug::HO_MANAGE_PRODUCTS, 'routes' => ['ho.categories.*']],
                    ['label' => 'Sub Categories', 'route' => 'ho.sub-categories.index', 'icon' => 'bi-folder2-open', 'permission' => PermissionSlug::HO_MANAGE_PRODUCTS, 'routes' => ['ho.sub-categories.*']],
                    ['label' => 'Brands', 'route' => 'ho.brands.index', 'icon' => 'bi-tag', 'permission' => PermissionSlug::HO_MANAGE_PRODUCTS, 'routes' => ['ho.brands.*']],
                    ['label' => 'Units', 'route' => 'ho.units.index', 'icon' => 'bi-rulers', 'permission' => PermissionSlug::HO_MANAGE_PRODUCTS, 'routes' => ['ho.units.*']],
                    ['label' => 'Products', 'route' => 'ho.products.index', 'icon' => 'bi-box', 'permission' => PermissionSlug::HO_MANAGE_PRODUCTS, 'routes' => ['ho.products.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Inventory',
                'icon' => 'bi-archive',
                'permission' => PermissionSlug::HO_MANAGE_STOCKS,
                'routes' => ['ho.stocks.*', 'ho.product-life.*'],
                'children' => [
                    ['label' => 'Stocks', 'route' => 'ho.stocks.index', 'icon' => 'bi-boxes', 'permission' => PermissionSlug::HO_MANAGE_STOCKS, 'routes' => ['ho.stocks.index']],
                    ['label' => 'Stock In', 'route' => 'ho.stocks.stock-in', 'icon' => 'bi-box-arrow-in-down', 'permission' => PermissionSlug::HO_MANAGE_STOCKS, 'routes' => ['ho.stocks.stock-in*']],
                    ['label' => 'Stock Out', 'route' => 'ho.stocks.stock-out', 'icon' => 'bi-box-arrow-up', 'permission' => PermissionSlug::HO_MANAGE_STOCKS, 'routes' => ['ho.stocks.stock-out*']],
                    ['label' => 'Product Life', 'route' => 'ho.product-life.index', 'icon' => 'bi-hourglass-split', 'permission' => PermissionSlug::HO_MANAGE_PRODUCT_LIFE, 'routes' => ['ho.product-life.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Reports',
                'icon' => 'bi-bar-chart-line',
                'permission' => PermissionSlug::HO_VIEW_REPORTS,
                'routes' => ['ho.reports.*'],
                'children' => [
                    ['label' => 'Product Life Usage', 'route' => 'ho.reports.product-life', 'icon' => 'bi-clock-history', 'permission' => PermissionSlug::HO_VIEW_REPORTS, 'routes' => ['ho.reports.product-life']],
                    ['label' => 'Category Purchase', 'route' => 'ho.reports.category-purchase', 'icon' => 'bi-pie-chart', 'permission' => PermissionSlug::HO_VIEW_REPORTS, 'routes' => ['ho.reports.category-purchase']],
                    ['label' => 'Multiple Usage', 'route' => 'ho.reports.multiple-usage', 'icon' => 'bi-exclamation-diamond', 'permission' => PermissionSlug::HO_VIEW_REPORTS, 'routes' => ['ho.reports.multiple-usage']],
                    ['label' => 'Usage by Category', 'route' => 'ho.reports.usage-by-category', 'icon' => 'bi-diagram-3', 'permission' => PermissionSlug::HO_VIEW_REPORTS, 'routes' => ['ho.reports.usage-by-category']],
                ],
            ],
            ['type' => 'link', 'label' => 'Notifications', 'route' => 'ho.notifications.index', 'icon' => 'bi-bell', 'permission' => PermissionSlug::HO_ACCESS, 'routes' => ['ho.notifications.*']],
        ],
    ],

    'admin' => [
        'label' => 'Main Menu',
        'items' => [
            ['type' => 'link', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'permission' => PermissionSlug::ADMIN_ACCESS, 'routes' => ['admin.dashboard']],
            [
                'type' => 'group',
                'label' => 'Master',
                'icon' => 'bi-building',
                'permission' => PermissionSlug::ADMIN_MANAGE_SITES,
                'routes' => ['admin.sites.*', 'admin.machines.*', 'admin.suppliers.*'],
                'any_permission' => [
                    PermissionSlug::ADMIN_MANAGE_SITES,
                    PermissionSlug::ADMIN_MANAGE_MACHINES,
                    PermissionSlug::ADMIN_MANAGE_SUPPLIERS,
                ],
                'children' => [
                    ['label' => 'Sites / Plants', 'route' => 'admin.sites.index', 'icon' => 'bi-geo-alt', 'permission' => PermissionSlug::ADMIN_MANAGE_SITES, 'routes' => ['admin.sites.*']],
                    ['label' => 'Machines', 'route' => 'admin.machines.index', 'icon' => 'bi-gear-wide-connected', 'permission' => PermissionSlug::ADMIN_MANAGE_MACHINES, 'routes' => ['admin.machines.*']],
                    ['label' => 'Suppliers', 'route' => 'admin.suppliers.index', 'icon' => 'bi-truck', 'permission' => PermissionSlug::ADMIN_MANAGE_SUPPLIERS, 'routes' => ['admin.suppliers.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Access Control',
                'icon' => 'bi-shield-lock',
                'permission' => PermissionSlug::ADMIN_MANAGE_ROLES,
                'routes' => ['admin.roles.*', 'admin.permissions.*', 'admin.users.*'],
                'any_permission' => [
                    PermissionSlug::ADMIN_MANAGE_ROLES,
                    PermissionSlug::ADMIN_MANAGE_PERMISSIONS,
                    PermissionSlug::ADMIN_MANAGE_USERS,
                ],
                'children' => [
                    ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'bi-shield-lock', 'permission' => PermissionSlug::ADMIN_MANAGE_ROLES, 'routes' => ['admin.roles.*']],
                    ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'icon' => 'bi-key', 'permission' => PermissionSlug::ADMIN_MANAGE_PERMISSIONS, 'routes' => ['admin.permissions.*']],
                    ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'bi-people', 'permission' => PermissionSlug::ADMIN_MANAGE_USERS, 'routes' => ['admin.users.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Product',
                'icon' => 'bi-box-seam',
                'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS,
                'routes' => ['admin.categories.*', 'admin.sub-categories.*', 'admin.brands.*', 'admin.units.*', 'admin.products.*'],
                'children' => [
                    ['label' => 'Categories', 'route' => 'admin.categories.index', 'icon' => 'bi-folder', 'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS, 'routes' => ['admin.categories.*']],
                    ['label' => 'Sub Categories', 'route' => 'admin.sub-categories.index', 'icon' => 'bi-folder2-open', 'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS, 'routes' => ['admin.sub-categories.*']],
                    ['label' => 'Brands', 'route' => 'admin.brands.index', 'icon' => 'bi-tag', 'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS, 'routes' => ['admin.brands.*']],
                    ['label' => 'Units', 'route' => 'admin.units.index', 'icon' => 'bi-rulers', 'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS, 'routes' => ['admin.units.*']],
                    ['label' => 'Products', 'route' => 'admin.products.index', 'icon' => 'bi-box', 'permission' => PermissionSlug::ADMIN_MANAGE_PRODUCTS, 'routes' => ['admin.products.*']],
                ],
            ],
            [
                'type' => 'group',
                'label' => 'Operation',
                'icon' => 'bi-eye',
                'permission' => PermissionSlug::HO_ACCESS,
                'routes' => ['ho.*'],
                'children' => [
                    ['label' => 'HO Dashboard', 'route' => 'ho.dashboard', 'icon' => 'bi-speedometer2', 'permission' => PermissionSlug::HO_ACCESS, 'routes' => ['ho.dashboard']],
                    ['label' => 'Product Requests', 'route' => 'ho.product-requests.index', 'icon' => 'bi-clipboard-check', 'permission' => PermissionSlug::HO_MANAGE_PRODUCT_REQUESTS, 'routes' => ['ho.product-requests.*']],
                    ['label' => 'Purchases & PO', 'route' => 'ho.purchases.index', 'icon' => 'bi-cart', 'permission' => PermissionSlug::HO_VIEW_PURCHASES, 'routes' => ['ho.purchases.*']],
                    ['label' => 'Stocks', 'route' => 'ho.stocks.index', 'icon' => 'bi-boxes', 'permission' => PermissionSlug::HO_MANAGE_STOCKS, 'routes' => ['ho.stocks.*']],
                    ['label' => 'Reports', 'route' => 'ho.reports.index', 'icon' => 'bi-bar-chart-line', 'permission' => PermissionSlug::HO_VIEW_REPORTS, 'routes' => ['ho.reports.*']],
                ],
            ],
            ['type' => 'link', 'label' => 'Notifications', 'route' => 'admin.notifications.index', 'icon' => 'bi-bell', 'permission' => PermissionSlug::ADMIN_ACCESS, 'routes' => ['admin.notifications.*']],
        ],
    ],

];
