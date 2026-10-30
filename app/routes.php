<?php
declare(strict_types=1);

/**
 * Route table: route name => page file, title, auth requirement and layout.
 */
function routes(): array
{
    return [
        'login'         => ['file' => 'pages/login.php', 'title' => 'Sign in', 'auth' => false, 'layout' => 'auth'],
        'dashboard'     => ['file' => 'pages/dashboard.php', 'title' => 'Dashboard', 'auth' => true],
        'orders'        => ['file' => 'pages/orders.php', 'title' => 'Orders', 'auth' => true],
        'orders/new'    => ['file' => 'pages/order_form.php', 'title' => 'New order', 'auth' => true],
        'orders/edit'   => ['file' => 'pages/order_form.php', 'title' => 'Edit order', 'auth' => true],
        'orders/show'   => ['file' => 'pages/order_show.php', 'title' => 'Order detail', 'auth' => true],
        'customers'     => ['file' => 'pages/customers.php', 'title' => 'Customers', 'auth' => true],
        'customers/new' => ['file' => 'pages/customer_form.php', 'title' => 'New customer', 'auth' => true],
        'customers/edit' => ['file' => 'pages/customer_form.php', 'title' => 'Edit customer', 'auth' => true],
        'customers/show' => ['file' => 'pages/customer_show.php', 'title' => 'Customer', 'auth' => true],
        'services'      => ['file' => 'pages/services.php', 'title' => 'Services', 'auth' => true],
        'payments'      => ['file' => 'pages/payments.php', 'title' => 'Payments', 'auth' => true],
        'employees'     => ['file' => 'pages/employees.php', 'title' => 'Staff', 'auth' => true],
        'reports'       => ['file' => 'pages/reports.php', 'title' => 'Reports', 'auth' => true],
        'settings'      => ['file' => 'pages/settings.php', 'title' => 'Settings', 'auth' => true, 'admin' => true],
        'invoice'       => ['file' => 'pages/invoice.php', 'title' => 'Receipt', 'auth' => true, 'layout' => 'print'],
    ];
}

function known_routes(): array
{
    return array_keys(routes());
}

function nav_items(): array
{
    return [
        ['dashboard', 'Dashboard', 'grid'],
        ['orders', 'Orders', 'basket'],
        ['customers', 'Customers', 'users'],
        ['services', 'Services', 'tag'],
        ['payments', 'Payments', 'cash'],
        ['employees', 'Staff', 'badge'],
        ['reports', 'Reports', 'chart'],
        ['settings', 'Settings', 'gear'],
    ];
}

function route_is_active(string $route, string $key): bool
{
    return $route === $key || str_starts_with($route, $key . '/');
}
