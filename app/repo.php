<?php
declare(strict_types=1);

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
                $cache[$row['skey']] = (string) $row['svalue'];
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    if (db_is_mysql()) {
        $sql = 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)';
    } else {
        $sql = 'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON CONFLICT(skey) DO UPDATE SET svalue = excluded.svalue';
    }
    db()->prepare($sql)->execute([$key, $value]);
}

function next_order_no(): string
{
    $max = (int) db()->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();
    return 'ORD-' . date('Y') . '-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

function next_customer_code(): string
{
    $max = (int) db()->query('SELECT COALESCE(MAX(id), 0) FROM customers')->fetchColumn();
    return 'CUST-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

function order_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT o.*,
                c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                c.address AS customer_address, c.code AS customer_code, c.notes AS customer_notes,
                e.name AS employee_name, e.role AS employee_role,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = o.id) AS paid
         FROM orders o
         LEFT JOIN customers c ON c.id = o.customer_id
         LEFT JOIN employees e ON e.id = o.employee_id
         WHERE o.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function order_payments(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY paid_at DESC, id DESC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function recent_orders(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT o.*, c.name AS customer_name,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = o.id) AS paid
         FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
         ORDER BY o.created_at DESC, o.id DESC LIMIT ' . max(1, $limit)
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function status_counts(): array
{
    $counts = ['received' => 0, 'processing' => 0, 'ready' => 0, 'delivered' => 0, 'cancelled' => 0];
    foreach (db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }
    return $counts;
}

function dashboard_stats(): array
{
    $pdo = db();
    $monthStart = date('Y-m-01 00:00:00');
    $nextMonth = date('Y-m-01 00:00:00', strtotime('first day of next month'));

    $ordersToday = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '" . today() . "'")->fetchColumn();
    $active = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('received','processing','ready')")->fetchColumn();
    $customers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $ready = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'ready'")->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> ? AND created_at >= ? AND created_at < ?');
    $stmt->execute(['cancelled', $monthStart, $nextMonth]);
    $monthRevenue = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= ? AND paid_at < ?');
    $stmt->execute([$monthStart, $nextMonth]);
    $monthCollected = (float) $stmt->fetchColumn();

    $outstanding = (float) $pdo->query(
        "SELECT COALESCE(SUM(GREATEST(o.total - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id), 0)),0)
         FROM orders o WHERE o.status <> 'cancelled'"
    )->fetchColumn();

    $avg = (float) $pdo->query("SELECT COALESCE(AVG(total),0) FROM orders WHERE status <> 'cancelled'")->fetchColumn();

    $paidCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders o WHERE o.status <> 'cancelled'
         AND (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id) >= o.total"
    )->fetchColumn();
    $openCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status <> 'cancelled'")->fetchColumn();

    $week = (float) $pdo->query(
        "SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled' AND created_at >= '" . date('Y-m-d 00:00:00', strtotime('-6 days')) . "'"
    )->fetchColumn();

    return [
        'orders_today'     => $ordersToday,
        'active_orders'    => $active,
        'customers'        => $customers,
        'ready'            => $ready,
        'month_revenue'    => $monthRevenue,
        'month_collected'  => $monthCollected,
        'outstanding'      => $outstanding,
        'avg_order'        => $avg,
        'week_revenue'     => $week,
        'paid_orders'      => $paidCount,
        'open_orders'      => $openCount,
    ];
}

function top_services(string $from, string $to, int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT oi.label, SUM(oi.qty) AS qty, SUM(oi.line_total) AS revenue, COUNT(DISTINCT oi.order_id) AS orders
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status <> ? AND o.created_at >= ? AND o.created_at < ?
         GROUP BY oi.label
         ORDER BY revenue DESC LIMIT ' . max(1, $limit)
    );
    $stmt->execute(['cancelled', $from, $to]);
    return $stmt->fetchAll();
}

function revenue_by_day(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT DATE(created_at) AS day, SUM(total) AS revenue, COUNT(*) AS orders
         FROM orders WHERE status <> ? AND created_at >= ? AND created_at < ?
         GROUP BY DATE(created_at) ORDER BY day'
    );
    $stmt->execute(['cancelled', $from, $to]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[substr((string) $row['day'], 0, 10)] = [
            'revenue' => (float) $row['revenue'],
            'orders'  => (int) $row['orders'],
        ];
    }

    // Fill every day in range so the chart never has holes.
    $series = [];
    $cursor = strtotime(substr($from, 0, 10));
    $end = strtotime(substr($to, 0, 10));
    while ($cursor !== false && $cursor <= $end && count($series) < 400) {
        $key = date('Y-m-d', $cursor);
        $series[$key] = $rows[$key] ?? ['revenue' => 0.0, 'orders' => 0];
        $cursor = strtotime('+1 day', $cursor);
    }
    return $series;
}

function payments_by_method(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT method, COUNT(*) AS payments, SUM(amount) AS total
         FROM payments WHERE paid_at >= ? AND paid_at < ?
         GROUP BY method ORDER BY total DESC'
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

function employee_performance(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT e.id, e.name, e.role,
                COUNT(o.id) AS orders,
                COALESCE(SUM(o.total), 0) AS revenue
         FROM employees e
         LEFT JOIN orders o ON o.employee_id = e.id AND o.status <> ? AND o.created_at >= ? AND o.created_at < ?
         GROUP BY e.id, e.name, e.role
         ORDER BY revenue DESC'
    );
    $stmt->execute(['cancelled', $from, $to]);
    return $stmt->fetchAll();
}

function customer_totals(int $customerId): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS spend,
                COALESCE(SUM(CASE WHEN status IN ('received','processing','ready') THEN 1 ELSE 0 END),0) AS active
         FROM orders WHERE customer_id = ? AND status <> 'cancelled'"
    );
    $stmt->execute([$customerId]);
    return $stmt->fetch() ?: ['orders' => 0, 'spend' => 0, 'active' => 0];
}

function outstanding_for_order(array $order): array
{
    $total = (float) $order['total'];
    $paid = (float) ($order['paid'] ?? 0);
    $meta = balance_meta($total, $paid);
    return ['total' => $total, 'paid' => $paid] + $meta;
}

function customers_list(): array
{
    return db()->query('SELECT * FROM customers ORDER BY name')->fetchAll();
}

function services_list(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM services' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function employees_list(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM employees' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function activity_feed(int $limit = 8): array
{
    $limit = max(1, $limit);
    return db()->query(
        "SELECT a.*, u.name AS user_name FROM activity a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC, a.id DESC LIMIT {$limit}"
    )->fetchAll();
}
