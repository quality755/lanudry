<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reports CSV export
|--------------------------------------------------------------------------
| Loaded by public/index.php before any markup, so headers can still be sent.
*/

$from = query('from', date('Y-m-01'));
$to = query('to', today());
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = today();
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$stmt = db()->prepare(
    'SELECT o.order_no, o.created_at, c.name AS customer, c.phone, e.name AS staff,
            o.status, o.subtotal, o.discount, o.delivery_fee, o.total,
            (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id) AS paid
     FROM orders o
     LEFT JOIN customers c ON c.id = o.customer_id
     LEFT JOIN employees e ON e.id = o.employee_id
     WHERE o.created_at >= ? AND o.created_at <= ?
     ORDER BY o.created_at ASC'
);
$stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);

$rows = [];
foreach ($stmt->fetchAll() as $row) {
    $total = (float) $row['total'];
    $paid = (float) $row['paid'];
    $rows[] = [
        $row['order_no'],
        substr((string) $row['created_at'], 0, 10),
        (string) ($row['customer'] ?? ''),
        (string) ($row['phone'] ?? ''),
        (string) ($row['staff'] ?? ''),
        status_meta((string) $row['status'])['label'],
        number_format((float) $row['subtotal'], 2, '.', ''),
        number_format((float) $row['discount'], 2, '.', ''),
        number_format((float) $row['delivery_fee'], 2, '.', ''),
        number_format($total, 2, '.', ''),
        number_format($paid, 2, '.', ''),
        number_format(round($total - $paid, 2), 2, '.', ''),
    ];
}

log_activity('Exported reports CSV', $from . ' to ' . $to);

csv_download(
    'laundry-orders-' . $from . '_to_' . $to . '.csv',
    ['Order', 'Date', 'Customer', 'Phone', 'Staff', 'Status', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Paid', 'Balance'],
    $rows
);
